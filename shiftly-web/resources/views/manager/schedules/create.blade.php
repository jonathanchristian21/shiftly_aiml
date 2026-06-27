@extends('layouts.manager')

@section('title', 'Generate Schedule')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Generate Schedule</h1>
    <p class="text-body text-gray-600 mt-2">Filter employee pool → GA generates candidates → RF evaluates</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
    <div class="stat-card">
        <div class="stat-label">TOTAL</div>
        <div class="stat-value">{{ $stats['total_employees'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">CLUSTERED</div>
        <div class="stat-value text-green-600">{{ $stats['clustered'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">DEPARTMENTS</div>
        <div class="stat-value text-blue-600">{{ $stats['departments'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">SELECTED</div>
        <div class="stat-value text-purple-600" id="selectedCount">0</div>
    </div>
</div>

@if($stats['clustered'] === 0)
<div class="alert alert-error mb-6">
    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </svg>
    <span>Run <a href="{{ route('manager.cluster.show') }}" class="font-semibold underline">K-Means Clustering</a> first.</span>
</div>
@endif

<form method="POST" action="{{ route('manager.schedules.generate') }}" id="generateForm">
    @csrf
    
    <div class="card p-6 mb-6">
        <h2 class="text-title mb-4">Parameters</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="stat-label block mb-2">START DATE</label>
                <input type="date" name="start_date" required value="{{ date('Y-m-d') }}" class="w-full text-body">
            </div>
            <div>
                <label class="stat-label block mb-2">DAYS</label>
                <input type="number" name="days" required min="1" max="31" value="7" class="w-full text-body">
            </div>
            <div>
                <label class="stat-label block mb-2">CANDIDATES</label>
                <select name="candidates" class="w-full text-body">
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" {{ $i === 3 ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>
    </div>

    <div class="card p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-title">Employee Pool</h2>
            <div class="flex items-center space-x-2">
                <button type="button" onclick="selectAll()" class="text-caption text-blue-600 hover:underline">SELECT ALL</button>
                <span class="text-gray-300">|</span>
                <button type="button" onclick="deselectAll()" class="text-caption text-gray-600 hover:underline">CLEAR</button>
            </div>
        </div>
        
        <div class="mb-4 flex gap-2 flex-wrap">
            <select id="filterDept" class="text-caption">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>
            <select id="filterEdu" class="text-caption">
                <option value="">All Education</option>
                <option value="PG">PG</option>
                <option value="UG">UG</option>
            </select>
            <select id="filterLevel" class="text-caption">
                <option value="">All Levels</option>
                @for($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>
            <select id="filterCluster" class="text-caption">
                <option value="">All Clusters</option>
                @for($i = 1; $i <= 4; $i++)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>
            <button type="button" onclick="applyFilter()" class="btn btn-secondary text-xs">FILTER</button>
        </div>

        <div class="overflow-auto" style="max-height: 400px;">
            <table class="table-minimal w-full">
                <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                    <tr>
                        <th class="w-12"><input type="checkbox" id="selectAllCb" onclick="toggleAll(this)"></th>
                        <th>CODE</th>
                        <th>NAME</th>
                        <th>DEPT</th>
                        <th>EDU</th>
                        <th>LVL</th>
                        <th>CLUSTER</th>
                    </tr>
                </thead>
                <tbody id="employeeTable">
                    @forelse($employees as $emp)
                    <tr data-dept="{{ $emp->department_id }}" data-edu="{{ $emp->education }}" data-level="{{ $emp->job_level }}" data-cluster="{{ $emp->cluster_label }}">
                        <td><input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" class="emp-cb" onchange="updateCount()"></td>
                        <td class="text-mono text-caption">{{ $emp->employee_code }}</td>
                        <td class="font-semibold">{{ $emp->name }}</td>
                        <td class="text-caption">{{ substr($emp->department->name, 0, 20) }}</td>
                        <td><span class="badge badge-{{ $emp->education === 'PG' ? 'success' : 'primary' }}">{{ $emp->education }}</span></td>
                        <td class="text-mono">{{ $emp->job_level }}</td>
                        <td><span class="badge badge-secondary">C{{ $emp->cluster_label }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-gray-400 py-8">No clustered employees</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end space-x-3">
        <a href="{{ route('manager.dashboard') }}" class="btn btn-secondary">CANCEL</a>
        <button type="submit" class="btn btn-primary" {{ $stats['clustered'] === 0 ? 'disabled' : '' }}>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            <span>GENERATE</span>
        </button>
    </div>
</form>

<script>
function updateCount() {
    document.getElementById('selectedCount').textContent = document.querySelectorAll('.emp-cb:checked').length;
}

function selectAll() {
    document.querySelectorAll('.emp-cb').forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') cb.checked = true;
    });
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('.emp-cb').forEach(cb => cb.checked = false);
    updateCount();
}

function toggleAll(src) {
    document.querySelectorAll('.emp-cb').forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') cb.checked = src.checked;
    });
    updateCount();
}

function applyFilter() {
    const dept = document.getElementById('filterDept').value;
    const edu = document.getElementById('filterEdu').value;
    const level = document.getElementById('filterLevel').value;
    const cluster = document.getElementById('filterCluster').value;
    
    document.querySelectorAll('#employeeTable tr').forEach(row => {
        if (!row.dataset.dept) return;
        const match = (!dept || row.dataset.dept === dept) &&
                     (!edu || row.dataset.edu === edu) &&
                     (!level || row.dataset.level === level) &&
                     (!cluster || row.dataset.cluster === cluster);
        row.style.display = match ? '' : 'none';
    });
    updateCount();
}

document.getElementById('generateForm').addEventListener('submit', function(e) {
    const count = document.querySelectorAll('.emp-cb:checked').length;
    if (count === 0) {
        e.preventDefault();
        alert('Select at least 1 employee');
        return;
    }
    if (!confirm(`Generate with ${count} employees?`)) {
        e.preventDefault();
    }
});
</script>
@endsection
