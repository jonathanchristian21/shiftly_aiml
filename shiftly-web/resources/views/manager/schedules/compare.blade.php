@extends('layouts.app')

@section('title', 'Compare Schedules')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Compare Candidates</h1>
    <p class="text-body text-gray-600 mt-2">GA generated {{ count($candidates) }} candidates • RF evaluated profitability</p>
</div>

<div class="card p-6 mb-6">
    <div class="text-caption text-gray-600 mb-2">SCHEDULE INFO</div>
    <div class="flex gap-6 text-body">
        <div><span class="text-gray-500">Period:</span> <span class="font-mono font-semibold">{{ $poolInfo['start_date'] }}</span> <span class="text-gray-400">({{ $poolInfo['days'] }} days)</span></div>
        <div><span class="text-gray-500">Pool:</span> <span class="font-mono font-semibold">{{ $poolInfo['employee_count'] }}</span> <span class="text-gray-400">employees</span></div>
    </div>
</div>

<div class="card p-6 mb-6">
    <h2 class="text-title mb-4">Candidates Comparison</h2>
    <div class="overflow-x-auto">
        <table class="table-minimal w-full">
            <thead>
                <tr>
                    <th>CANDIDATE</th>
                    <th>GA FITNESS</th>
                    <th>RF SCORE</th>
                    <th>TOTAL SALARY</th>
                    <th>ACTIVE EMPS</th>
                    <th>ASSIGNMENTS</th>
                    <th>CLUSTER BAL</th>
                    <th>VIOLATIONS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                @foreach($candidates as $candidate)
                <tr>
                    <td><span class="badge badge-secondary font-mono">{{ substr($candidate['candidate_id'], -3) }}</span></td>
                    <td class="font-mono font-semibold">{{ number_format($candidate['summary']['ga_fitness'], 1) }}</td>
                    <td class="font-mono font-semibold text-green-600">{{ number_format($candidate['rf_profit_score'], 1) }}%</td>
                    <td class="font-mono">${{ number_format($candidate['summary']['total_salary'] / 1000000, 2) }}M</td>
                    <td class="font-mono">{{ $candidate['summary']['active_employees'] }}</td>
                    <td class="font-mono">{{ $candidate['summary']['total_assignments'] }}</td>
                    <td class="font-mono">{{ number_format($candidate['summary']['cluster_balance'] * 100, 1) }}%</td>
                    <td>
                        <span class="badge badge-{{ $candidate['summary']['hard_violation_count'] > 0 ? 'danger' : 'success' }}">
                            H:{{ $candidate['summary']['hard_violation_count'] }}
                        </span>
                        <span class="badge badge-warning">S:{{ $candidate['summary']['soft_violation_count'] }}</span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('manager.schedules.publish') }}" class="inline" onsubmit="return confirm('Publish this schedule?')">
                            @csrf
                            <input type="hidden" name="candidate_id" value="{{ $candidate['candidate_id'] }}">
                            <button type="submit" class="btn btn-success btn-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>PUBLISH</span>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card p-6">
    <h3 class="text-headline mb-3">How to Choose?</h3>
    <div class="space-y-2 text-caption text-gray-600">
        <div><strong>GA Fitness:</strong> Constraint satisfaction score (higher = fewer violations)</div>
        <div><strong>RF Profit Score:</strong> Financial profitability prediction (higher = more cost-efficient)</div>
        <div><strong>Recommendation:</strong> Choose candidate with <span class="text-green-600 font-semibold">highest RF Score</span> and <span class="text-red-600 font-semibold">H:0</span> (no hard violations)</div>
    </div>
</div>

<div class="mt-6 flex justify-end">
    <a href="{{ route('manager.schedules.create') }}" class="btn btn-secondary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span>BACK TO POOL</span>
    </a>
</div>
@endsection
