@extends('layouts.app')

@section('title', 'Schedule Detail')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Schedule #{{ $schedule->id }}</h1>
    <p class="text-body text-gray-600 mt-2">{{ $schedule->start_date->format('M d') }} - {{ $schedule->end_date->format('M d, Y') }} ({{ $schedule->days }} days)</p>
</div>

@if($schedule->selectedCandidate)
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-label">STATUS</div>
        <span class="badge badge-{{ $schedule->status === 'published' ? 'success' : 'secondary' }}">{{ strtoupper($schedule->status) }}</span>
    </div>
    <div class="stat-card">
        <div class="stat-label">EMPLOYEES</div>
        <div class="stat-value text-blue-600">{{ $schedule->selectedCandidate->active_employees }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">TOTAL SALARY</div>
        <div class="stat-value text-green-600 text-2xl">${{ number_format($schedule->selectedCandidate->total_salary / 1000000, 2) }}M</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">RF SCORE</div>
        <div class="stat-value text-purple-600">{{ $schedule->selectedCandidate->rf_profit_score ? number_format($schedule->selectedCandidate->rf_profit_score, 1) . '%' : '-' }}</div>
    </div>
</div>

<div class="card p-6 mb-6">
    <h2 class="text-title mb-4">Metrics</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-caption">
        <div>
            <div class="text-gray-500 mb-1">GA FITNESS</div>
            <div class="font-mono font-semibold">{{ number_format($schedule->selectedCandidate->ga_fitness, 1) }}</div>
        </div>
        <div>
            <div class="text-gray-500 mb-1">ASSIGNMENTS</div>
            <div class="font-mono font-semibold">{{ $schedule->selectedCandidate->total_assignments }}</div>
        </div>
        <div>
            <div class="text-gray-500 mb-1">VIOLATIONS</div>
            <div>
                <span class="badge badge-{{ $schedule->selectedCandidate->hard_violation_count > 0 ? 'danger' : 'success' }}">H:{{ $schedule->selectedCandidate->hard_violation_count }}</span>
                <span class="badge badge-warning">S:{{ $schedule->selectedCandidate->soft_violation_count }}</span>
            </div>
        </div>
    </div>
</div>

<div class="card p-6">
    <h2 class="text-title mb-4">Schedule Assignments ({{ $schedule->selectedCandidate->entries->count() }})</h2>
    <div class="overflow-x-auto">
        <table class="table-minimal w-full">
            <thead>
                <tr>
                    <th>DATE</th>
                    <th>EMPLOYEE</th>
                    <th>DEPARTMENT</th>
                    <th>SHIFT</th>
                    <th>CLUSTER</th>
                    <th>SENIOR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedule->selectedCandidate->entries->sortBy('shift_date')->groupBy('shift_date') as $date => $dayAssignments)
                    @foreach($dayAssignments as $entry)
                    <tr>
                        @if($loop->first)
                        <td rowspan="{{ $dayAssignments->count() }}" class="font-mono text-caption border-r">
                            {{ \Carbon\Carbon::parse($date)->format('M d, D') }}
                        </td>
                        @endif
                        <td class="font-semibold">{{ $entry->employee->name }}</td>
                        <td class="text-caption">{{ $entry->department->name }}</td>
                        <td>
                            <span class="badge badge-{{ 
                                $entry->shift === 'Pagi' ? 'warning' : 
                                ($entry->shift === 'Sore' ? 'primary' : 
                                ($entry->shift === 'Malam' ? 'secondary' : 'success')) 
                            }}">{{ $entry->shift }}</span>
                        </td>
                        <td class="font-mono text-caption">{{ $entry->cluster_label !== null ? 'C' . $entry->cluster_label : '-' }}</td>
                        <td class="text-caption">{{ $entry->is_senior_snapshot ? 'Yes' : 'No' }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card p-6">
    <p class="text-gray-500">No candidate selected for this schedule.</p>
</div>
@endif

<div class="mt-6 flex justify-between">
    <a href="{{ route('manager.schedules.index') }}" class="btn btn-secondary">BACK TO HISTORY</a>
    @if($schedule->status !== 'archived')
    <form method="POST" action="{{ route('manager.schedules.destroy', $schedule) }}" onsubmit="return confirm('Archive this schedule?')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-secondary text-red-600">ARCHIVE</button>
    </form>
    @endif
</div>
@endsection
