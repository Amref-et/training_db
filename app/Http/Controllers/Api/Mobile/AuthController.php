<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureIsNotRateLimited($request);

        $email = mb_strtolower(trim((string) $data['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! Hash::check((string) $data['password'], (string) $user->password)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $abilities = $this->abilitiesForUser($user);
        $expiresAt = $this->tokenExpiresAt();
        $deviceName = trim((string) ($data['device_name'] ?? ''));
        $token = $user->createToken(
            $deviceName !== '' ? $deviceName : $this->defaultDeviceName($request),
            $abilities,
            $expiresAt
        );

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $expiresAt?->toIso8601String(),
                'abilities' => $abilities,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $this->userPayload($user),
                'token' => [
                    'name' => $user?->currentAccessToken()?->name,
                    'abilities' => $user?->currentAccessToken()?->abilities ?? [],
                    'expires_at' => $user?->currentAccessToken()?->expires_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    private function userPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roleNames()->values()->all(),
            'permissions' => $user->permissions()->pluck('slug')->values()->all(),
        ];
    }

    private function abilitiesForUser(User $user): array
    {
        $permissions = $user->permissions()->pluck('slug')->values()->all();
        $hasAny = fn (array $slugs): bool => collect($slugs)->contains(
            fn (string $slug): bool => in_array($slug, $permissions, true)
        );

        $referenceResources = [
            'regions',
            'zones',
            'woredas',
            'organizations',
            'training_organizers',
            'trainings',
            'projects',
            'project_categories',
            'training_categories',
            'training_rounds',
        ];

        $referenceRead = collect($referenceResources)
            ->map(fn (string $resource): string => $resource.'.view')
            ->push('dashboard.view')
            ->all();

        $referenceWrite = collect($referenceResources)
            ->flatMap(fn (string $resource): array => [
                $resource.'.create',
                $resource.'.update',
                $resource.'.delete',
            ])
            ->all();

        $abilities = [];

        if ($hasAny($referenceRead)) {
            $abilities[] = 'reference-data:read';
        }

        if ($hasAny($referenceWrite)) {
            $abilities[] = 'reference-data:write';
        }

        if ($hasAny(['participants.view'])) {
            $abilities[] = 'participants:read';
        }

        if ($hasAny(['participants.create', 'participants.update', 'participants.delete'])) {
            $abilities[] = 'participants:write';
        }

        if ($hasAny(['training_events.view'])) {
            $abilities[] = 'training-events:read';
        }

        if ($hasAny(['training_events.create', 'training_events.update', 'training_events.delete'])) {
            $abilities[] = 'training-events:write';
        }

        return array_values(array_unique($abilities));
    }

    private function tokenExpiresAt(): ?\Illuminate\Support\Carbon
    {
        $days = (int) config('mobile.token_expiration_days', 30);

        return $days > 0 ? now()->addDays($days) : null;
    }

    private function defaultDeviceName(Request $request): string
    {
        $agent = trim((string) $request->userAgent());

        return $agent !== ''
            ? 'Mobile app - '.Str::limit($agent, 80, '')
            : 'Mobile app';
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }
}
