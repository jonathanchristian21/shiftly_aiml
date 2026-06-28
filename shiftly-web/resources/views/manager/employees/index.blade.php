@extends('layouts.manager')

@section('title', 'Employees')

@section('content')
<div class="mb-8 flex justify-between items-start">
    <div>
        <h1 class="text-display">Employees Management</h1>
        <p class="text-caption mt-2">Manage hospital staff and their information</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('manager.employees.import') }}" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <span>Import CSV</span>
        </a>
        <a href="{{ route('manager.employees.create') }}" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add Employee</span>
        </a>
    </div>
</div>

<!-- Filter Card -->
<div class="card p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="text-tiny mb-2">Search</label>
            <input type="text" id="searchFilter" placeholder="Name or code..." class="w-full" oninput="applyFilters()">
        </div>
        <div>
            <label class="text-tiny mb-2">Department</label>
            <select id="departmentFilter" class="w-full" onchange="applyFilters()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-tiny mb-2">Education</label>
            <select id="educationFilter" class="w-full" onchange="applyFilters()">
                <option value="">All Education</option>
                <option value="UG">UG</option>
                <option value="PG">PG</option>
            </select>
        </div>
        <div>
            <label class="text-tiny mb-2">Job Level</label>
            <select id="jobLevelFilter" class="w-full" onchange="applyFilters()">
                <option value="">All Levels</option>
                @for($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}">Level {{ $i }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="text-tiny mb-2">Cluster</label>
            <select id="clusterFilter" class="w-full" onchange="applyFilters()">
                <option value="">All Clusters</option>
                @for($i = 1; $i <= 4; $i++)
                    <option value="{{ $i }}">Cluster {{ $i }}</option>
                @endfor
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" onclick="clearFilters()" class="btn btn-secondary w-full">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Clear</span>
            </button>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table-minimal">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Department</th>
                    <th>Education</th>
                    <th>Job Level</th>
                    <th>Salary</th>
                    <th>Cluster</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                <tr class="employee-row"
                    data-code="{{ strtolower($employee->employee_code) }}"
                    data-name="{{ strtolower($employee->name) }}"
                    data-dept="{{ $employee->department_id }}"
                    data-education="{{ $employee->education }}"
                    data-level="{{ $employee->job_level }}"
                    data-cluster="{{ $employee->cluster_label ?? '' }}">
                    <td class="mono font-semibold text-ink">{{ $employee->employee_code }}</td>
                    <td class="font-medium text-ink">{{ $employee->name }}</td>
                    <td class="mono">{{ $employee->age }}</td>
                    <td class="text-caption">{{ $employee->department->name }}</td>
                    <td>
                        <span class="badge {{ $employee->education === 'PG' ? 'badge-success' : 'badge-primary' }}">
                            {{ $employee->education }}
                        </span>
                    </td>
                    <td class="mono">{{ $employee->job_level }}</td>
                    <td class="mono font-semibold text-emerald-600">${{ number_format($employee->salary, 2) }}</td>
                    <td>
                        @if($employee->cluster_label !== null)
                            <span class="badge badge-secondary">C{{ $employee->cluster_label }}</span>
                        @else
                            <span class="text-caption">-</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('manager.employees.edit', $employee) }}" class="px-3 py-1.5 border border-gray-200 rounded-md text-sky hover:border-sky hover:bg-sky/5 font-semibold text-caption transition-colors">
                                Edit
                            </a>
                            <form action="{{ route('manager.employees.destroy', $employee) }}" method="POST" class="inline" onsubmit="return confirm('Delete this employee?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 border border-gray-200 rounded-md text-red-500 hover:border-red-500 hover:bg-red-50 font-semibold text-caption transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-caption py-8">No employees found. Import CSV to get started.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('searchFilter').value.toLowerCase();
    const dept = document.getElementById('departmentFilter').value;
    const education = document.getElementById('educationFilter').value;
    const level = document.getElementById('jobLevelFilter').value;
    const cluster = document.getElementById('clusterFilter').value;
    
    let visibleCount = 0;
    document.querySelectorAll('.employee-row').forEach(row => {
        const matchSearch = !search || 
            row.dataset.code.includes(search) || 
            row.dataset.name.includes(search);
        const matchDept = !dept || row.dataset.dept === dept;
        const matchEducation = !education || row.dataset.education === education;
        const matchLevel = !level || row.dataset.level === level;
        const matchCluster = !cluster || row.dataset.cluster === cluster;
        
        const isVisible = matchSearch && matchDept && matchEducation && matchLevel && matchCluster;
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
    });
}

function clearFilters() {
    document.getElementById('searchFilter').value = '';
    document.getElementById('departmentFilter').value = '';
    document.getElementById('educationFilter').value = '';
    document.getElementById('jobLevelFilter').value = '';
    document.getElementById('clusterFilter').value = '';
    applyFilters();
}

// Set filters from URL params on load
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('department')) document.getElementById('departmentFilter').value = params.get('department');
    if (params.get('cluster')) document.getElementById('clusterFilter').value = params.get('cluster');
    applyFilters();
});
</script>
@endsection
