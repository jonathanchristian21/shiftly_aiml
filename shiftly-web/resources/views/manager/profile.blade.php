@extends('layouts.manager')

@section('title', 'Manager Profile')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Manager Profile</h1>
</div>

@if(session('success'))
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <ul>
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card p-6 mb-6">
    <h2 class="text-title mb-4">Personal Information</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="text-caption">Name</label>
            <p class="text-body font-semibold text-ink">{{ $user->name }}</p>
        </div>
        <div>
            <label class="text-caption">Email</label>
            <p class="text-body font-semibold text-ink">{{ $user->email }}</p>
        </div>
        <div>
            <label class="text-caption">Role</label>
            <p class="text-body font-semibold text-ink">
                <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                    {{ ucfirst($user->role) }}
                </span>
            </p>
        </div>
    </div>
</div>

<!-- Change Credentials Section -->
<div class="card p-6 mb-6">
    <h2 class="text-title mb-4">Change Credentials</h2>
    <form method="POST" action="{{ route('manager.profile.update') }}" class="space-y-6">
        @csrf
        @method('PUT')
        
        <div>
            <label>Current Password *</label>
            <input type="password" name="current_password" required class="w-full text-body">
            <p class="text-xs text-gray-500 mt-1">Required to make any changes</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label>New Username (optional)</label>
                <input type="text" name="name" value="{{ $user->name }}" class="w-full text-body">
            </div>
            
            <div>
                <label>New Email (optional)</label>
                <input type="email" name="email" value="{{ $user->email }}" class="w-full text-body">
            </div>
            
            <div>
                <label>New Password (optional)</label>
                <input type="password" name="new_password" class="w-full text-body">
            </div>
        </div>

        <div>
            <label>Confirm New Password</label>
            <input type="password" name="new_password_confirmation" class="w-full text-body">
        </div>

        <button type="submit" class="btn btn-primary">
            Update Credentials
        </button>
    </form>
</div>
@endsection
