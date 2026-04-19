<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class EnvSettingsController extends Controller
{
    public function edit(): View
    {
        $groups = $this->groups();
        $currentValues = $this->readEnvValues();

        return view('admin.settings.env', [
            'groups' => $groups,
            'currentValues' => $currentValues,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $groups = $this->groups();
        $rules = [];
        $oldValues = $this->readEnvValues();

        foreach ($groups as $fields) {
            foreach ($fields as $field) {
                $rules[$field['key']] = $field['rule'];
            }
        }

        $validated = $request->validate($rules);
        $newValues = [];

        foreach ($groups as $fields) {
            foreach ($fields as $field) {
                $key = $field['key'];
                if (($field['type'] ?? '') === 'boolean') {
                    $newValues[$key] = $request->boolean($key) ? 'true' : 'false';
                } else {
                    $newValues[$key] = trim((string) ($validated[$key] ?? ''));
                }
            }
        }

        $this->writeEnvValues($newValues);

        // Reload config values that depend on .env for subsequent requests.
        Artisan::call('config:clear');

        $changedKeys = collect(array_keys($newValues))
            ->filter(fn (string $key) => ($oldValues[$key] ?? null) !== ($newValues[$key] ?? null))
            ->values()
            ->all();

        $this->audit()->logCustom('.env settings updated', 'env.updated', [
            'old_values' => collect($changedKeys)->mapWithKeys(fn (string $key) => [$key => $oldValues[$key] ?? null])->all(),
            'new_values' => collect($changedKeys)->mapWithKeys(fn (string $key) => [$key => $newValues[$key] ?? null])->all(),
        ]);

        return redirect()->route('admin.settings.env.edit')->with('success', '.env settings updated successfully.');
    }

    private function groups(): array
    {
        return [
            'Application' => [
                ['key' => 'APP_NAME', 'label' => 'Application Name', 'type' => 'text', 'rule' => 'required|string|max:120'],
                ['key' => 'APP_ENV', 'label' => 'Environment', 'type' => 'select', 'options' => ['local', 'development', 'staging', 'production'], 'rule' => 'required|string|max:50'],
                ['key' => 'APP_DEBUG', 'label' => 'Debug Mode', 'type' => 'boolean', 'rule' => 'required|boolean'],
                ['key' => 'APP_URL', 'label' => 'Application URL', 'type' => 'url', 'rule' => 'nullable|url|max:255'],
                ['key' => 'APP_TIMEZONE', 'label' => 'Timezone', 'type' => 'text', 'rule' => 'nullable|string|max:100'],
                ['key' => 'LOG_LEVEL', 'label' => 'Log Level', 'type' => 'select', 'options' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], 'rule' => 'required|string|max:20'],
            ],
            'Session' => [
                ['key' => 'SESSION_DRIVER', 'label' => 'Session Driver', 'type' => 'select', 'options' => ['file', 'cookie', 'database', 'redis', 'array'], 'rule' => 'required|string|max:50'],
                ['key' => 'SESSION_LIFETIME', 'label' => 'Session Lifetime (minutes)', 'type' => 'number', 'rule' => 'required|integer|min:1|max:10080'],
            ],
            'Database' => [
                ['key' => 'DB_CONNECTION', 'label' => 'DB Connection', 'type' => 'text', 'rule' => 'required|string|max:50'],
                ['key' => 'DB_HOST', 'label' => 'DB Host', 'type' => 'text', 'rule' => 'required|string|max:255'],
                ['key' => 'DB_PORT', 'label' => 'DB Port', 'type' => 'number', 'rule' => 'required|integer|min:1|max:65535'],
                ['key' => 'DB_DATABASE', 'label' => 'DB Database', 'type' => 'text', 'rule' => 'required|string|max:255'],
                ['key' => 'DB_USERNAME', 'label' => 'DB Username', 'type' => 'text', 'rule' => 'required|string|max:255'],
                ['key' => 'DB_PASSWORD', 'label' => 'DB Password', 'type' => 'text', 'rule' => 'nullable|string|max:255'],
            ],
            'Mail' => [
                ['key' => 'MAIL_MAILER', 'label' => 'Mail Driver', 'type' => 'select', 'options' => ['smtp', 'sendmail', 'log', 'array', 'failover'], 'rule' => 'required|string|max:50'],
                ['key' => 'MAIL_HOST', 'label' => 'Mail Host', 'type' => 'text', 'rule' => 'nullable|string|max:255'],
                ['key' => 'MAIL_PORT', 'label' => 'Mail Port', 'type' => 'number', 'rule' => 'nullable|integer|min:1|max:65535'],
                ['key' => 'MAIL_USERNAME', 'label' => 'Mail Username', 'type' => 'text', 'rule' => 'nullable|string|max:255'],
                ['key' => 'MAIL_PASSWORD', 'label' => 'Mail Password', 'type' => 'text', 'rule' => 'nullable|string|max:255'],
                ['key' => 'MAIL_FROM_ADDRESS', 'label' => 'From Address', 'type' => 'email', 'rule' => 'nullable|email|max:255'],
                ['key' => 'MAIL_FROM_NAME', 'label' => 'From Name', 'type' => 'text', 'rule' => 'nullable|string|max:255'],
            ],
        ];
    }

    private function envPath(): string
    {
        return base_path('.env');
    }

    private function readEnvValues(): array
    {
        $path = $this->envPath();
        if (! File::exists($path)) {
            return [];
        }

        $content = (string) File::get($path);
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $values = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $values[$matches[1]] = $this->decodeEnvValue($matches[2]);
        }

        return $values;
    }

    private function writeEnvValues(array $newValues): void
    {
        $path = $this->envPath();
        $content = File::exists($path) ? (string) File::get($path) : '';
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $updated = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];
            if (! array_key_exists($key, $newValues)) {
                continue;
            }

            $lines[$index] = $key.'='.$this->encodeEnvValue($newValues[$key]);
            $updated[$key] = true;
        }

        foreach ($newValues as $key => $value) {
            if (isset($updated[$key])) {
                continue;
            }

            $lines[] = $key.'='.$this->encodeEnvValue($value);
        }

        $output = implode($eol, $lines);
        if ($output !== '' && ! str_ends_with($output, $eol)) {
            $output .= $eol;
        }

        File::put($path, $output, true);
    }

    private function decodeEnvValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }

        return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }

    private function encodeEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_\\.\\-\\/:@]+$/', $value) === 1) {
            return $value;
        }

        return '"'.addcslashes($value, "\\\"").'"';
    }
}
