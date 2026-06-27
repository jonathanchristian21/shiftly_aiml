@extends('layouts.manager')

@section('title', 'Candidate Detail')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Candidate {{ substr($enrichedCandidate['candidate_id'], -3) }}</h1>
    <p class="text-body text-gray-600 mt-2">{{ $poolInfo['start_date'] }} ({{ $poolInfo['days'] }} days) • {{ $poolInfo['employee_count'] }} employees in pool</p>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="stat-label">GA FITNESS</div>
        <div class="stat-value text-blue-600">{{ number_format($enrichedCandidate['summary']['ga_fitness'], 1) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">RF SCORE</div>
        <div class="stat-value text-purple-600">{{ number_format($enrichedCandidate['rf_profit_score'], 1) }}%</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">TOTAL SALARY</div>
        <div class="stat-value text-green-600 text-2xl">${{ number_format($enrichedCandidate['summary']['total_salary'] / 1000000, 2) }}M</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">EMPLOYEES</div>
        <div class="stat-value text-orange-600">{{ $enrichedCandidate['summary']['active_employees'] }}</div>
    </div>
</div>

<div class="card p-6 mb-6">
    <h2 class="text-title mb-4">Metrics</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-caption">
        <div>
            <div class="text-gray-500 mb-1">ASSIGNMENTS</div>
            <div class="font-mono font-semibold">{{ $enrichedCandidate['summary']['total_assignments'] }}</div>
        </div>
        <div>
            <div class="text-gray-500 mb-1">VIOLATIONS</div>
            <div>
                <span class="badge badge-{{ $enrichedCandidate['summary']['hard_violation_count'] > 0 ? 'danger' : 'success' }}">H:{{ $enrichedCandidate['summary']['hard_violation_count'] }}</span>
                <span class="badge badge-warning">S:{{ $enrichedCandidate['summary']['soft_violation_count'] }}</span>
            </div>
        </div>
        @if(isset($enrichedCandidate['summary']['cluster_balance']))
        <div>
            <div class="text-gray-500 mb-1">CLUSTER BALANCE</div>
            <div class="font-mono font-semibold">{{ number_format($enrichedCandidate['summary']['cluster_balance'], 2) }}</div>
        </div>
        @endif
    </div>
</div>

<div class="card p-6">
    <h2 class="text-title mb-4">Schedule Assignments ({{ count($enrichedCandidate['assignments']) }})</h2>
    <div class="overflow-x-auto">
        <table class="table-minimal w-full">
            <thead>
                <tr>
                    <th>DATE</th>
                    <th>EMPLOYEE</th>
                    <th>DEPARTMENT</th>
                    <th>SHIFT</th>
                    <th>SENIOR</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $groupedAssignments = collect($enrichedCandidate['assignments'])->groupBy('date')->sortKeys();
                @endphp
                @foreach($groupedAssignments as $date => $dayAssignments)
                    @foreach($dayAssignments as $assignment)
                    <tr>
                        @if($loop->first)
                        <td rowspan="{{ $dayAssignments->count() }}" class="font-mono text-caption border-r">
                            {{ \Carbon\Carbon::parse($date)->format('M d, D') }}
                        </td>
                        @endif
                        <td class="font-semibold">{{ $assignment['employee_name'] }}</td>
                        <td class="text-caption">{{ $assignment['department_name'] }}</td>
                        <td>
                            <span class="badge badge-{{ 
                                $assignment['shift'] === 'Pagi' ? 'danger' : 
                                ($assignment['shift'] === 'Sore' ? 'warning' : 
                                ($assignment['shift'] === 'Malam' ? 'primary' : 'success')) 
                            }}">{{ $assignment['shift'] }}</span>
                        </td>
                        <td class="text-caption">{{ ($assignment['is_senior_snapshot'] ?? false) ? 'Yes' : 'No' }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 flex justify-between">
    <a href="{{ route('manager.schedules.compare') }}" class="btn btn-secondary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span>BACK TO COMPARE</span>
    </a>
    <form method="POST" action="{{ route('manager.schedules.publish') }}" class="inline" onsubmit="return confirm('Publish this schedule?')">
        @csrf
        <input type="hidden" name="candidate_id" value="{{ $enrichedCandidate['candidate_id'] }}">
        <button type="submit" class="btn btn-success">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>PUBLISH SCHEDULE</span>
        </button>
    </form>
</div>
@endsection
