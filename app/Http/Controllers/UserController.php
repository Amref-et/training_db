<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    private const USER_IMPORT_TEMPLATE_HEADERS = ['name', 'email', 'role'];
    private const USER_IMPORT_REPORT_DIRECTORY = 'user-import-results';

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('roles')->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(),
            'roles' => Role::query()->orderBy('name')->get(),
            'selectedRole' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles([$data['role_id']]);
        $user->load('roles');
        $this->audit()->logCustom('User created', 'user.created', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'auditable_label' => $user->email,
            'new_values' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roleNames()->all(),
            ],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::USER_IMPORT_TEMPLATE_HEADERS);
        }, 'users-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $result = $this->importUsersFromCsv((string) $validated['import_file']->getRealPath());
        } catch (\RuntimeException $exception) {
            return back()
                ->withErrors(['import_file' => $exception->getMessage()])
                ->withInput();
        }

        $report = $this->writeUserImportReport($result['rows']);

        $this->audit()->logCustom('Users imported', 'users.imported', [
            'auditable_type' => User::class,
            'metadata' => [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'report_file' => $report['file_name'] ?? null,
            ],
        ]);

        $redirect = redirect()
            ->route('admin.users.index')
            ->with('success', 'User import completed: '.$result['created'].' created, '.$result['skipped'].' skipped.');

        if ($report !== null) {
            $redirect->with('user_import_report', $report);
        }

        if ($result['created'] > 0) {
            $redirect->with('warning', 'Download the import result CSV now and share the temporary passwords with the new users. Passwords are not shown again elsewhere.');
        } elseif ($result['skipped'] > 0) {
            $redirect->with('warning', 'No users were created. Download the import result CSV to review skipped rows.');
        }

        return $redirect;
    }

    public function downloadImportReport(string $report): StreamedResponse
    {
        $fileName = basename($report);
        if (! preg_match('/^users-import-results-\d{8}-\d{6}-[A-Za-z0-9]{6}\.csv$/', $fileName)) {
            abort(404);
        }

        $path = self::USER_IMPORT_REPORT_DIRECTORY.'/'.$fileName;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user->load('roles'),
            'roles' => Role::query()->orderBy('name')->get(),
            'selectedRole' => $user->primaryRole()?->id,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $beforeValues = [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roleNames()->all(),
        ];

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ]);

        $payload = ['name' => $data['name'], 'email' => $data['email']];

        if (! blank($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$data['role_id']]);
        $user->load('roles');
        $this->audit()->logCustom('User updated', 'user.updated', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'auditable_label' => $user->email,
            'old_values' => $beforeValues,
            'new_values' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roleNames()->all(),
            ],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->hasRole('Admin') && User::query()->whereHas('roles', fn ($query) => $query->where('name', 'Admin'))->count() <= 1) {
            return back()->with('error', 'At least one admin account must remain.');
        }

        $beforeValues = [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roleNames()->all(),
        ];
        $userId = $user->id;
        $userEmail = $user->email;
        $user->delete();
        $this->audit()->logCustom('User deleted', 'user.deleted', [
            'auditable_type' => User::class,
            'auditable_id' => $userId,
            'auditable_label' => $userEmail,
            'old_values' => $beforeValues,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    private function importUsersFromCsv(string $path): array
    {
        $handle = $path !== '' ? fopen($path, 'r') : false;
        if ($handle === false) {
            throw new \RuntimeException('Unable to read import file.');
        }

        $created = 0;
        $skipped = 0;
        $reportRows = [];

        try {
            $headerRow = fgetcsv($handle);

            if (! is_array($headerRow) || empty($headerRow)) {
                throw new \RuntimeException('Invalid CSV file: missing header row.');
            }

            $headerMap = [];
            foreach ($headerRow as $index => $column) {
                $normalized = $this->normalizeCsvHeader((string) $column);
                if ($normalized !== '' && ! array_key_exists($normalized, $headerMap)) {
                    $headerMap[$normalized] = $index;
                }
            }

            foreach (self::USER_IMPORT_TEMPLATE_HEADERS as $requiredHeader) {
                if (! array_key_exists($requiredHeader, $headerMap)) {
                    throw new \RuntimeException('Invalid CSV file: required columns are name, email, role.');
                }
            }

            $lineNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isBlankCsvRow($row)) {
                    continue;
                }

                $name = $this->csvCell($row, $headerMap, ['name']);
                $email = Str::lower($this->csvCell($row, $headerMap, ['email']));
                $roleValue = $this->csvCell($row, $headerMap, ['role', 'role_name', 'role_id']);
                $reportRow = [
                    'line_number' => $lineNumber,
                    'name' => $name,
                    'email' => $email,
                    'role' => $roleValue,
                    'status' => 'skipped',
                    'temporary_password' => '',
                    'message' => '',
                ];

                $skipReason = null;

                if ($name === '') {
                    $skipReason = 'Name is required.';
                } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $skipReason = 'Valid email is required.';
                } elseif (User::query()->where('email', $email)->exists()) {
                    $skipReason = 'A user with this email already exists.';
                }

                $role = $skipReason === null ? $this->resolveImportRole($roleValue) : null;
                if ($skipReason === null && ! $role) {
                    $skipReason = 'Role was not found. Use a role name or role ID.';
                }

                if ($skipReason !== null) {
                    $skipped++;
                    $reportRow['message'] = $skipReason;
                    $reportRows[] = $reportRow;

                    continue;
                }

                $temporaryPassword = $this->temporaryPassword();
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $temporaryPassword,
                ]);
                $user->syncRoles([$role->id]);

                $created++;
                $reportRow['status'] = 'created';
                $reportRow['role'] = $role->name;
                $reportRow['temporary_password'] = $temporaryPassword;
                $reportRow['message'] = 'Created with generated temporary password.';
                $reportRows[] = $reportRow;
            }
        } finally {
            fclose($handle);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'rows' => $reportRows,
        ];
    }

    private function resolveImportRole(string $roleValue): ?Role
    {
        $roleValue = trim($roleValue);
        if ($roleValue === '') {
            return null;
        }

        if (ctype_digit($roleValue)) {
            return Role::query()->find((int) $roleValue);
        }

        return Role::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($roleValue)])
            ->first();
    }

    private function writeUserImportReport(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $fileName = 'users-import-results-'.now()->format('Ymd-His').'-'.Str::random(6).'.csv';
        $path = self::USER_IMPORT_REPORT_DIRECTORY.'/'.$fileName;
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create user import result report.');
        }

        try {
            fputcsv($stream, [
                'line_number',
                'name',
                'email',
                'role',
                'status',
                'temporary_password',
                'message',
            ]);

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row['line_number'],
                    $row['name'],
                    $row['email'],
                    $row['role'],
                    $row['status'],
                    $row['temporary_password'],
                    $row['message'],
                ]);
            }

            rewind($stream);
            Storage::disk('local')->put($path, stream_get_contents($stream));
        } finally {
            fclose($stream);
        }

        return [
            'file_name' => $fileName,
            'url' => route('admin.users.import-results', ['report' => $fileName]),
        ];
    }

    private function normalizeCsvHeader(string $header): string
    {
        $cleanHeader = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return trim((string) Str::of($cleanHeader)->lower()->replace(['-', ' '], '_')->replace('__', '_'));
    }

    private function csvCell(array $row, array $headerMap, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeCsvHeader((string) $key);
            if (! array_key_exists($normalized, $headerMap)) {
                continue;
            }

            $index = (int) $headerMap[$normalized];
            $value = $row[$index] ?? null;

            if ($value === null) {
                continue;
            }

            return trim((string) $value);
        }

        return '';
    }

    private function isBlankCsvRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function temporaryPassword(): string
    {
        return 'Tmp-'.Str::random(12);
    }
}
