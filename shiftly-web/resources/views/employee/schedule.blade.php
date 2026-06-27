@extends('layouts.employee')

@section('title', 'My Schedule')

@section('content')
<div class="mb-8">
    <h1 class="text-display">My Schedule</h1>
</div>

@if($employee)
<div class="card p-6 mb-6">
    <h2 class="text-title">{{ $employee->name }}</h2>
    <p class="text-caption mt-1">{{ $employee->department->name }} - {{ $employee->education }}</p>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table-minimal">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Department</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schedules as $schedule)
                <tr>
                    <td class="text-body font-semibold text-ink">{{ $schedule->shift_date->format('d M Y') }}</td>
                    <td>
                    <span class="badge badge-{{ 
                        $schedule->shift === 'Pagi' ? 'danger' : 
                        ($schedule->shift === 'Sore' ? 'warning' : 
                        ($schedule->shift === 'Malam' ? 'primary' : 'success')) 
                    }}">
                        {{ $schedule->shift }}
                    </span>
                    </td>
                    <td class="text-caption">{{ $schedule->department->name }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center text-caption py-8">No schedule found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $schedules->links() }}</div>
@else
<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
    No employee profile found for your account.
</div>
@endif
@endsection
