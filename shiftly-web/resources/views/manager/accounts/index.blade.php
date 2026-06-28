@extends('layouts.manager')

@section('title', 'Manager Accounts')

@section('content')
<div class="fade-in">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-display mb-2">Manager Accounts</h1>
            <p class="text-caption">Manage manager accounts for the system</p>
        </div>
        <a href="{{ route('manager.accounts.create') }}" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Manager
        </a>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="table-minimal">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($managers as $manager)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="icon-box" style="width: 32px; height: 32px; background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);">
                                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold">{{ $manager->name }}</div>
                                        @if($manager->id === auth()->id())
                                            <span class="badge badge-primary" style="font-size: 9px; padding: 2px 6px;">You</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="mono text-caption">{{ $manager->email }}</td>
                            <td class="text-caption">{{ $manager->created_at->format('M d, Y') }}</td>
                            <td>
                                @if($manager->id === auth()->id())
                                    @if(count($managers) > 1)
                                        <form method="POST" action="{{ route('manager.accounts.destroy', $manager) }}" 
                                              onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone and you will be logged out immediately.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA;">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Delete My Account
                                            </button>
                                        </form>
                                    @else
                                        <button disabled class="btn btn-sm opacity-50 cursor-not-allowed" title="Cannot delete the last manager account" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA;">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete My Account
                                        </button>
                                    @endif
                                @else
                                    <span class="text-caption">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-caption py-8">No manager accounts found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
