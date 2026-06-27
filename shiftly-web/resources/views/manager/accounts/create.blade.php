@extends('layouts.manager')

@section('title', 'Add Manager Account')

@section('content')
<div class="fade-in">
    <div class="mb-6">
        <a href="{{ route('manager.accounts.index') }}" class="btn btn-secondary btn-sm mb-4">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Accounts
        </a>
        <h1 class="text-display mb-2">Add Manager Account</h1>
        <p class="text-caption">Create a new manager account for the system</p>
    </div>

    <div class="card" style="max-width: 600px;">
        <div class="p-6">
            <form method="POST" action="{{ route('manager.accounts.store') }}">
                @csrf

                <div class="mb-5">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <p class="text-caption" style="color: #DC2626; margin-top: 4px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <p class="text-caption" style="color: #DC2626; margin-top: 4px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <p class="text-caption" style="margin-top: 4px;">Minimum 8 characters</p>
                    @error('password')
                        <p class="text-caption" style="color: #DC2626; margin-top: 4px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required>
                    @error('password_confirmation')
                        <p class="text-caption" style="color: #DC2626; margin-top: 4px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Create Manager Account
                    </button>
                    <a href="{{ route('manager.accounts.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
