@extends('layouts.admin')

@section('eyebrow', 'Access Control')
@section('title', $user->exists ? 'Edit User' : 'Create User')
@section('subtitle', 'Assign a role and update account details.')

@section('content')
<div class="panel p-4">
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="row g-3">
        @csrf
        @if($user->exists) @method('PUT') @endif
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" type="text" name="name" value="{{ old('name', $user->name) }}"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}"></div>
        <div class="col-md-6"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep current password)' : '' }}</label><input class="form-control" type="password" name="password"></div>
        <div class="col-md-6"><label class="form-label">Confirm Password</label><input class="form-control" type="password" name="password_confirmation"></div>
        <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role_id"><option value="">Select role</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string) old('role_id', $selectedRole) === (string) $role->id)>{{ $role->name }}</option>@endforeach</select></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-dark" type="submit">Save User</button><a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div>
@endsection
