@extends('layouts.admin')

@section('eyebrow', 'Account')
@section('title', 'Profile')
@section('subtitle', 'Update your account details.')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel p-4">
            <form method="POST" action="{{ route('profile.update') }}" class="row g-3">
                @csrf
                @method('PATCH')
                <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" type="text" name="name" value="{{ old('name', $user->name) }}"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}"></div>
                <div class="col-12"><button class="btn btn-dark" type="submit">Save Profile</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4">
            <h2 class="h5">Delete Account</h2>
            <p class="text-secondary">This permanently removes your account.</p>
            <form method="POST" action="{{ route('profile.destroy') }}" class="d-grid gap-3">
                @csrf
                @method('DELETE')
                <div><label class="form-label">Current Password</label><input class="form-control" type="password" name="password"></div>
                <button class="btn btn-outline-danger" onclick="return confirm('Delete your account?')">Delete Account</button>
            </form>
        </div>
    </div>
</div>
@endsection
