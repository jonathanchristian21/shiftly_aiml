@extends('layouts.manager')

@section('title', 'Shift Requirements')

@section('content')
<div class="mb-8 flex justify-between items-start">
    <div>
        <h1 class="text-display">Shift Requirements</h1>
        <p class="text-caption mt-2">Configure minimum staff requirements per shift</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openBulkModal()" class="btn btn-success">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span>Bulk Setup</span>
        </button>
        <a href="{{ route('manager.shift-requirements.create') }}" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add Single</span>
        </a>
    </div>
</div>

<!-- Info Card -->
<div class="card p-5 mb-6" style="background: linear-gradient(135deg, #EFF5FF 0%, #DBEAFE 100%); border-color: #BFDBFE;">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-sky shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
            <div class="font-semibold text-ink mb-1">About Shift Requirements</div>
            <p class="text-caption">Define minimum staff and senior requirements for each department and shift. These constraints will be used by the Genetic Algorithm during schedule generation. Use <strong>Bulk Setup</strong> to quickly configure multiple departments at once.</p>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="card overflow-hidden">
    <form id="bulkDeleteForm" method="POST" action="{{ route('manager.shift-requirements.destroy', 0) }}">
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
                        <th>Department</th>
                        <th>Shift</th>
                        <th>Required Staff</th>
                        <th>Required Senior</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requirements as $req)
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $req->id }}" class="w-4 h-4 row-checkbox" onchange="updateBulkDelete()">
                        </td>
                        <td class="font-semibold text-ink">{{ $req->department->name }}</td>
                        <td>
                            <span class="badge 
                                {{ $req->shift === 'Pagi' ? 'badge-danger' : '' }}
                                {{ $req->shift === 'Sore' ? 'badge-warning' : '' }}
                                {{ $req->shift === 'Malam' ? 'badge-primary' : '' }}">
                                {{ $req->shift }}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span class="mono font-semibold text-sky">{{ $req->required_staff }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                                <span class="mono font-semibold text-emerald-600">{{ $req->required_senior }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $req->is_active ? 'badge-success' : 'badge-secondary' }}">
                                {{ $req->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('manager.shift-requirements.edit', $req) }}" class="px-3 py-1.5 border border-gray-200 rounded-md text-sky hover:border-sky hover:bg-sky/5 font-semibold text-caption transition-colors">
                                    Edit
                                </a>
                                @if($req->is_active)
                                    <button type="submit" form="deactivateForm_{{ $req->id }}" class="px-3 py-1.5 border border-yellow-200 rounded-md text-yellow-600 hover:border-yellow-400 hover:bg-yellow-50 font-semibold text-caption transition-colors">
                                        Deactivate
                                    </button>
                                @else
                                    <button type="submit" form="activateForm_{{ $req->id }}" class="px-3 py-1.5 border border-green-200 rounded-md text-emerald-600 hover:border-emerald-400 hover:bg-emerald-50 font-semibold text-caption transition-colors">
                                        Activate
                                    </button>
                                @endif
                                <button type="submit" form="deleteForm_{{ $req->id }}" class="px-3 py-1.5 border border-gray-200 rounded-md text-red-500 hover:border-red-500 hover:bg-red-50 font-semibold text-caption transition-colors">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-caption py-8">No shift requirements configured. Use Bulk Setup to get started quickly.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- Bulk Setup Modal -->
<div id="bulkModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" onclick="if(event.target === this) closeBulkModal()">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-title">Bulk Setup Shift Requirements</h2>
            <button onclick="closeBulkModal()" class="text-ink-mute hover:text-ink">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('manager.shift-requirements.bulk') }}">
            @csrf
            
            <!-- Modal Body -->
            <div class="px-6 py-4 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                <!-- Step 1: Select Departments -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-body font-semibold">1. Select Departments</label>
                        <button type="button" onclick="toggleAllDepartments(this)" class="text-caption text-sky hover:text-sky-dark font-semibold">
                            Select All
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($departments as $dept)
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="departments[]" value="{{ $dept->id }}" class="w-4 h-4 dept-checkbox">
                            <div class="flex-1">
                                <div class="font-semibold text-ink">{{ $dept->name }}</div>
                                <div class="text-caption">{{ $dept->employees_count }} employees</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="divider mb-6"></div>

                <!-- Step 2: Configure Shifts -->
                <div>
                    <label class="text-body font-semibold mb-3 block">2. Configure Shifts</label>
                    <div class="space-y-4">
                        <!-- Pagi -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <label class="flex items-center gap-3 mb-3 cursor-pointer">
                                <input type="checkbox" name="shifts[0][enabled]" value="1" class="w-5 h-5" checked>
                                <span class="badge badge-danger">PAGI</span>
                                <span class="text-caption">(Morning Shift)</span>
                            </label>
                            <div class="grid grid-cols-2 gap-4 ml-8">
                                <div>
                                    <label class="text-tiny mb-2 block">Min Staff</label>
                                    <input type="number" name="shifts[0][required_staff]" min="0" value="6" class="w-full">
                                </div>
                                <div>
                                    <label class="text-tiny mb-2 block">Min Senior</label>
                                    <input type="number" name="shifts[0][required_senior]" min="0" value="1" class="w-full">
                                </div>
                            </div>
                        </div>

                        <!-- Sore -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <label class="flex items-center gap-3 mb-3 cursor-pointer">
                                <input type="checkbox" name="shifts[1][enabled]" value="1" class="w-5 h-5" checked>
                                <span class="badge badge-warning">SORE</span>
                                <span class="text-caption">(Afternoon Shift)</span>
                            </label>
                            <div class="grid grid-cols-2 gap-4 ml-8">
                                <div>
                                    <label class="text-tiny mb-2 block">Min Staff</label>
                                    <input type="number" name="shifts[1][required_staff]" min="0" value="6" class="w-full">
                                </div>
                                <div>
                                    <label class="text-tiny mb-2 block">Min Senior</label>
                                    <input type="number" name="shifts[1][required_senior]" min="0" value="1" class="w-full">
                                </div>
                            </div>
                        </div>

                        <!-- Malam -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <label class="flex items-center gap-3 mb-3 cursor-pointer">
                                <input type="checkbox" name="shifts[2][enabled]" value="1" class="w-5 h-5" checked>
                                <span class="badge badge-primary">MALAM</span>
                                <span class="text-caption">(Night Shift)</span>
                            </label>
                            <div class="grid grid-cols-2 gap-4 ml-8">
                                <div>
                                    <label class="text-tiny mb-2 block">Min Staff</label>
                                    <input type="number" name="shifts[2][required_staff]" min="0" value="4" class="w-full">
                                </div>
                                <div>
                                    <label class="text-tiny mb-2 block">Min Senior</label>
                                    <input type="number" name="shifts[2][required_senior]" min="0" value="1" class="w-full">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button type="button" onclick="closeBulkModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Create Requirements</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openBulkModal() {
    document.getElementById('bulkModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeBulkModal() {
    document.getElementById('bulkModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function toggleAllDepartments(btn) {
    const checkboxes = document.querySelectorAll('.dept-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    btn.textContent = allChecked ? 'Select All' : 'Deselect All';
}

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
        if (confirm(`Are you sure you want to delete ${count} shift requirement(s)?`)) {
            form.action = "{{ route('manager.shift-requirements.destroy', 0) }}";
            // method is already DELETE
            form.submit();
        }
    } else if (action === 'deactivate') {
        if (confirm(`Are you sure you want to deactivate ${count} shift requirement(s)?`)) {
            form.action = "{{ route('manager.shift-requirements.bulk-deactivate') }}";
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'POST';
            form.appendChild(methodInput);
            form.submit();
        }
    } else if (action === 'activate') {
        if (confirm(`Are you sure you want to activate ${count} shift requirement(s)?`)) {
            form.action = "{{ route('manager.shift-requirements.bulk-activate') }}";
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'POST';
            form.appendChild(methodInput);
            form.submit();
        }
    }
}

// Close on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBulkModal();
});
</script>

<!-- Hidden forms for individual actions to prevent nesting -->
@foreach($requirements as $req)
<form id="activateForm_{{ $req->id }}" action="{{ route('manager.shift-requirements.activate', $req) }}" method="POST" onsubmit="return confirm('Activate this shift requirement?')" style="display: none;">
    @csrf
</form>
<form id="deactivateForm_{{ $req->id }}" action="{{ route('manager.shift-requirements.deactivate', $req) }}" method="POST" onsubmit="return confirm('Deactivate this shift requirement?')" style="display: none;">
    @csrf
</form>
<form id="deleteForm_{{ $req->id }}" action="{{ route('manager.shift-requirements.destroy', $req) }}" method="POST" onsubmit="return confirm('Delete this shift requirement?')" style="display: none;">
    @csrf @method('DELETE')
</form>
@endforeach

@endsection
