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
            <div>
                <h2 class="text-title">Employee Pool Selection</h2>
                <p class="text-caption text-ink-mute mt-1">Select employees to include in schedule generation</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="selectAllVisible()" class="btn btn-success btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Select All
                </button>
                <button type="button" onclick="deselectAll()" class="btn btn-secondary btn-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Clear
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
            <div>
                <label class="text-tiny mb-1 block">Department</label>
                <select id="filterDept" class="w-full" onchange="applyFilters()">
                    <option value="">All</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-tiny mb-1 block">Cluster</label>
                <select id="filterCluster" class="w-full" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="1">Cluster 1</option>
                    <option value="2">Cluster 2</option>
                    <option value="3">Cluster 3</option>
                    <option value="4">Cluster 4</option>
                </select>
            </div>
            <div>
                <label class="text-tiny mb-1 block">Education</label>
                <select id="filterEdu" class="w-full" onchange="applyFilters()">
                    <option value="">All</option>
                    <option value="PG">PG</option>
                    <option value="UG">UG</option>
                </select>
            </div>
            <div>
                <label class="text-tiny mb-1 block">Job Level</label>
                <select id="filterLevel" class="w-full" onchange="applyFilters()">
                    <option value="">All</option>
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}">Level {{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="text-tiny mb-1 block">Search</label>
                <input type="text" id="searchBox" placeholder="Name or code..." class="w-full" oninput="applyFilters()">
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto" style="max-height: 500px;">
            <table class="table-minimal">
                <thead class="sticky top-0 bg-white z-10">
                    <tr>
                        <th class="w-12">
                            <input type="checkbox" id="selectAllCb" class="w-4 h-4" onchange="toggleAll(this)">
                        </th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Education</th>
                        <th>Level</th>
                        <th>Cluster</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr class="employee-row"
                        data-id="{{ $emp->id }}"
                        data-dept="{{ $emp->department_id }}"
                        data-edu="{{ $emp->education }}"
                        data-level="{{ $emp->job_level }}"
                        data-cluster="{{ $emp->cluster_label }}"
                        data-name="{{ strtolower($emp->name) }}"
                        data-code="{{ strtolower($emp->employee_code) }}">
                        <td>
                            <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" class="emp-cb w-4 h-4" onchange="updateCount()">
                        </td>
                        <td class="mono font-semibold">{{ $emp->employee_code }}</td>
                        <td class="font-medium">{{ $emp->name }}</td>
                        <td class="text-caption">{{ $emp->department->name }}</td>
                        <td>
                            <span class="badge {{ $emp->education === 'PG' ? 'badge-success' : 'badge-primary' }}">
                                {{ $emp->education }}
                            </span>
                        </td>
                        <td class="mono">{{ $emp->job_level }}</td>
                        <td>
                            <span class="badge 
                                {{ $emp->cluster_label == 1 ? 'badge-success' : '' }}
                                {{ $emp->cluster_label == 2 ? 'badge-primary' : '' }}
                                {{ $emp->cluster_label == 3 ? 'badge-warning' : '' }}
                                {{ $emp->cluster_label == 4 ? 'badge-secondary' : '' }}">
                                C{{ $emp->cluster_label }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-caption py-8">No clustered employees. Run K-Means Clustering first.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 pt-4 border-t flex items-center justify-between">
            <span class="text-caption">Showing <span id="visibleCount" class="font-semibold">0</span> employees</span>
            <span class="text-body font-semibold">Selected: <span id="selectedDisplay" class="text-sky">0</span></span>
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
    const checked = document.querySelectorAll('.emp-cb:checked').length;
    const total = document.querySelectorAll('.employee-row').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('selectedDisplay').textContent = checked;
    
    // Update select all checkbox
    const selectAllCb = document.getElementById('selectAllCb');
    if (selectAllCb) {
        const visible = document.querySelectorAll('.employee-row:not([style*="display: none"])');
        const visibleChecked = Array.from(visible).filter(row => row.querySelector('.emp-cb').checked).length;
        selectAllCb.checked = visible.length > 0 && visibleChecked === visible.length;
    }
}

function toggleAll(checkbox) {
    document.querySelectorAll('.employee-row').forEach(row => {
        if (row.style.display !== 'none') {
            row.querySelector('.emp-cb').checked = checkbox.checked;
        }
    });
    updateCount();
}

function applyFilters() {
    const dept = document.getElementById('filterDept').value;
    const edu = document.getElementById('filterEdu').value;
    const level = document.getElementById('filterLevel').value;
    const cluster = document.getElementById('filterCluster').value;
    const search = document.getElementById('searchBox').value.toLowerCase();
    
    let visibleCount = 0;
    document.querySelectorAll('.employee-row').forEach(row => {
        const matchDept = !dept || row.dataset.dept === dept;
        const matchEdu = !edu || row.dataset.edu === edu;
        const matchLevel = !level || row.dataset.level === level;
        const matchCluster = !cluster || row.dataset.cluster === cluster;
        const matchSearch = !search || 
            row.dataset.name.includes(search) || 
            row.dataset.code.includes(search);
        
        const match = matchDept && matchEdu && matchLevel && matchCluster && matchSearch;
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });
    
    document.getElementById('visibleCount').textContent = visibleCount;
    updateCount();
}

function selectAllVisible() {
    document.querySelectorAll('.employee-row').forEach(row => {
        if (row.style.display !== 'none') {
            row.querySelector('.emp-cb').checked = true;
        }
    });
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('.emp-cb').forEach(cb => cb.checked = false);
    updateCount();
}

document.getElementById('generateForm').addEventListener('submit', function(e) {
    const count = document.querySelectorAll('.emp-cb:checked').length;
    if (count === 0) {
        e.preventDefault();
        alert('Please select at least 1 employee');
        return;
    }
    if (!confirm(`Generate schedule with ${count} employees?`)) {
        e.preventDefault();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    applyFilters();
    updateCount();
});
</script>
@endsection
