@extends('layouts.manager')

@section('title', 'Departments')

@section('content')
<div class="mb-8 flex justify-between items-start">
    <div>
        <h1 class="text-display">Departments Management</h1>
        <p class="text-caption mt-2">Manage hospital departments and divisions</p>
    </div>
    <a href="{{ route('manager.departments.create') }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Add Department</span>
    </a>
</div>

<!-- Table Card -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table-minimal">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Employees</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                <tr>
                    <td class="font-semibold text-ink">{{ $dept->name }}</td>
                    <td class="mono text-caption">{{ $dept->code ?? '-' }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="mono font-semibold">{{ $dept->employees_count }}</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $dept->is_active ? 'badge-success' : 'badge-secondary' }}">
                            {{ $dept->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('manager.departments.edit', $dept) }}" class="text-sky hover:text-sky-hover font-semibold text-caption">
                                Edit
                            </a>
                            <form action="{{ route('manager.departments.destroy', $dept) }}" method="POST" class="inline" onsubmit="return confirm('Deactivate this department?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-600 font-semibold text-caption">
                                    Deactivate
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-caption py-8">No departments found. Departments are created automatically during employee import.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- Pagination Footer -->
    @if($departments->hasPages())
    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
        {{ $departments->links() }}
    </div>
    @endif
</div>
@endsection
