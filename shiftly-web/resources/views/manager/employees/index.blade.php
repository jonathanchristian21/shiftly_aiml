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
    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="text-tiny mb-2">Search</label>
            <input type="text" name="search" placeholder="Name or code..." value="{{ request('search') }}" class="w-full">
        </div>
        <div>
            <label class="text-tiny mb-2">Department</label>
            <select name="department" class="w-full">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-tiny mb-2">Education</label>
            <select name="education" class="w-full">
                <option value="">All Education</option>
                <option value="UG" {{ request('education') === 'UG' ? 'selected' : '' }}>UG</option>
                <option value="PG" {{ request('education') === 'PG' ? 'selected' : '' }}>PG</option>
            </select>
        </div>
        <div>
            <label class="text-tiny mb-2">Job Level</label>
            <select name="job_level" class="w-full">
                <option value="">All Levels</option>
                @for($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}" {{ request('job_level') == $i ? 'selected' : '' }}>Level {{ $i }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="text-tiny mb-2">Cluster</label>
            <select name="cluster" class="w-full">
                <option value="">All Clusters</option>
                @for($i = 1; $i <= 4; $i++)
                    <option value="{{ $i }}" {{ request('cluster') == $i ? 'selected' : '' }}>Cluster {{ $i }}</option>
                @endfor
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span>Filter</span>
            </button>
        </div>
    </form>
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
                <tr>
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
                            <form action="{{ route('manager.employees.destroy', $employee) }}" method="POST" class="inline" onsubmit="return confirm('Deactivate this employee?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 border border-gray-200 rounded-md text-red-500 hover:border-red-500 hover:bg-red-50 font-semibold text-caption transition-colors">
                                    Deactivate
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
    
    <!-- Pagination Footer -->
    @if($employees->hasPages())
    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
        {{ $employees->links() }}
    </div>
    @endif
</div>
@endsection
