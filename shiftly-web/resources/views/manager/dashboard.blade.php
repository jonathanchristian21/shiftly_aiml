@extends('layouts.manager')

@section('title', 'Dashboard')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Dashboard</h1>
    <p class="text-caption mt-2">Welcome back, {{ auth()->user()->name }}</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <!-- Total Employees -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
        </div>
        <div class="stat-label">Total Employees</div>
        <div class="stat-value text-blue-600">{{ $stats['total_employees'] }}</div>
    </div>

    <!-- Clustered Employees -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                </svg>
            </div>
        </div>
        <div class="stat-label">Clustered Employees</div>
        <div class="stat-value text-emerald-600">{{ $stats['clustered_employees'] }}</div>
    </div>

    <!-- Total Schedules -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
        </div>
        <div class="stat-label">Total Schedules</div>
        <div class="stat-value text-purple-600">{{ $stats['total_schedules'] }}</div>
    </div>

    <!-- Published Schedules -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <div class="w-12 h-12 bg-amber-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <div class="stat-label">Published Schedules</div>
        <div class="stat-value text-amber-600">{{ $stats['published_schedules'] }}</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <a href="{{ route('manager.employees.import') }}" class="card p-5 card-hover flex items-center gap-4" style="text-decoration: none;">
        <div class="w-12 h-12 bg-sky-soft rounded-lg flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-sky" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
        </div>
        <div>
            <div class="font-semibold text-ink mb-1">Import Employees</div>
            <div class="text-caption">Upload CSV data</div>
        </div>
    </a>

    <a href="{{ route('manager.cluster.show') }}" class="card p-5 card-hover flex items-center gap-4" style="text-decoration: none;">
        <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        </div>
        <div>
            <div class="font-semibold text-ink mb-1">Run Clustering</div>
            <div class="text-caption">K-Means AI</div>
        </div>
    </a>

    <a href="{{ route('manager.schedules.create') }}" class="card p-5 card-hover flex items-center gap-4" style="text-decoration: none;">
        <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
        </div>
        <div>
            <div class="font-semibold text-ink mb-1">Generate Schedule</div>
            <div class="text-caption">GA + Random Forest</div>
        </div>
    </a>
</div>

<!-- Recent Employees -->
<div class="card p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-title">Recent Employees</h2>
        <a href="{{ route('manager.employees.index') }}" class="text-caption text-sky hover:text-sky-hover font-semibold flex items-center gap-2" style="text-decoration: none;">
            <span>View all</span>
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="table-minimal w-full">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Education</th>
                    <th>Cluster</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentEmployees as $employee)
                <tr>
                    <td class="mono font-semibold text-ink">{{ $employee->employee_code }}</td>
                    <td class="font-medium text-ink">{{ $employee->name }}</td>
                    <td class="text-caption">{{ $employee->department->name }}</td>
                    <td>
                        <span class="badge {{ $employee->education === 'PG' ? 'badge-success' : 'badge-primary' }}">
                            {{ $employee->education }}
                        </span>
                    </td>
                    <td>
                        @if($employee->cluster_label !== null)
                            <span class="badge badge-secondary">C{{ $employee->cluster_label }}</span>
                        @else
                            <span class="text-caption">Not clustered</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-caption py-8">No employees found. Import CSV to get started.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
