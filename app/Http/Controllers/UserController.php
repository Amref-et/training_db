<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
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

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
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

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->hasRole('Admin') && User::query()->whereHas('roles', fn ($query) => $query->where('name', 'Admin'))->count() <= 1) {
            return back()->with('error', 'At least one admin account must remain.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }
}
