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
    <form id="bulkDeleteForm" method="POST" action="{{ route('manager.departments.bulk') }}">
        @csrf
        @method('DELETE')
        
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="selectAll" class="w-4 h-4" onchange="toggleAllRows(this)">
                    <span class="text-caption font-medium">Select All</span>
                </label>
                <span id="selectedCount" class="text-caption text-ink-mute"></span>
            </div>
            <div id="bulkActions" style="display: none;" class="flex gap-2">
                <button type="button" onclick="confirmBulkAction('activate')" class="btn btn-success btn-sm" style="background: #D1FAE5; color: #059669; border: 1px solid #A7F3D0;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Activate</span>
                </button>
                <button type="button" onclick="confirmBulkAction('deactivate')" class="btn btn-warning btn-sm" style="background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <span>Deactivate</span>
                </button>
                <button type="button" onclick="confirmBulkAction('delete')" class="btn btn-danger btn-sm" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Delete</span>
                </button>
            </div>
        </div>

    <div class="overflow-x-auto">
        <table class="table-minimal">
            <thead>
                <tr>
                    <th class="w-12"></th>
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
                    <td>
                        <input type="checkbox" name="ids[]" value="{{ $dept->id }}" class="w-4 h-4 row-checkbox" onchange="updateBulkDelete()">
                    </td>
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
                            <a href="{{ route('manager.employees.index', ['department' => $dept->id]) }}" class="px-3 py-1.5 border border-gray-200 rounded-md text-purple-600 hover:border-purple-500 hover:bg-purple-50 font-semibold text-caption transition-colors">
                                <svg class="w-3.5 h-3.5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View
                            </a>
                            <a href="{{ route('manager.departments.edit', $dept) }}" class="px-3 py-1.5 border border-gray-200 rounded-md text-sky hover:border-sky hover:bg-sky/5 font-semibold text-caption transition-colors">
                                Edit
                            </a>
                            @if($dept->is_active)
                                <button type="submit" form="deactivateForm_{{ $dept->id }}" class="px-3 py-1.5 border border-yellow-200 rounded-md text-yellow-600 hover:border-yellow-400 hover:bg-yellow-50 font-semibold text-caption transition-colors">
                                    Deactivate
                                </button>
                            @else
                                <button type="submit" form="activateForm_{{ $dept->id }}" class="px-3 py-1.5 border border-green-200 rounded-md text-emerald-600 hover:border-emerald-400 hover:bg-emerald-50 font-semibold text-caption transition-colors">
                                    Activate
                                </button>
                            @endif
                            <button type="submit" form="deleteForm_{{ $dept->id }}" class="px-3 py-1.5 border border-gray-200 rounded-md text-red-500 hover:border-red-500 hover:bg-red-50 font-semibold text-caption transition-colors">
                                Delete
                            </button>
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
    </form>
</div>

<script>
function toggleAllRows(checkbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateBulkDelete();
}

function updateBulkDelete() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const count = checkboxes.length;
    const actions = document.getElementById('bulkActions');
    const counter = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAll');
    
    if (count > 0) {
        actions.style.display = 'flex';
        counter.textContent = `${count} selected`;
    } else {
        actions.style.display = 'none';
        counter.textContent = '';
    }
    
    const allCheckboxes = document.querySelectorAll('.row-checkbox');
    selectAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
}

function confirmBulkAction(action) {
    const form = document.getElementById('bulkDeleteForm');
    const count = document.querySelectorAll('.row-checkbox:checked').length;
    
    if (action === 'delete') {
        if (confirm(`Are you sure you want to soft-delete ${count} department(s)?`)) {
            form.action = "{{ route('manager.departments.bulk') }}";
            // method is already DELETE
            form.submit();
        }
    } else if (action === 'deactivate') {
        if (confirm(`Are you sure you want to deactivate ${count} department(s)?`)) {
            form.action = "{{ route('manager.departments.bulk-deactivate') }}";
            // form method must be POST for deactivate
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'POST';
            form.appendChild(methodInput);
            form.submit();
        }
    } else if (action === 'activate') {
        if (confirm(`Are you sure you want to activate ${count} department(s)? Their shift requirements will also be activated.`)) {
            form.action = "{{ route('manager.departments.bulk-activate') }}";
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'POST';
            form.appendChild(methodInput);
            form.submit();
        }
    }
}
</script>

<!-- Hidden forms for individual actions to prevent nesting -->
@foreach($departments as $dept)
<form id="activateForm_{{ $dept->id }}" action="{{ route('manager.departments.activate', $dept) }}" method="POST" onsubmit="return confirm('Activate this department and its shift requirements?')" style="display: none;">
    @csrf
</form>
<form id="deactivateForm_{{ $dept->id }}" action="{{ route('manager.departments.deactivate', $dept) }}" method="POST" onsubmit="return confirm('Deactivate this department?')" style="display: none;">
    @csrf
</form>
<form id="deleteForm_{{ $dept->id }}" action="{{ route('manager.departments.destroy', $dept) }}" method="POST" onsubmit="return confirm('Delete this department? (Soft delete, employees kept)')" style="display: none;">
    @csrf @method('DELETE')
</form>
@endforeach
@endsection
