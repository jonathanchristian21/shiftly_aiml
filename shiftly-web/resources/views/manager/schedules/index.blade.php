@extends('layouts.manager')

@section('title', 'Schedule History')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Schedule History</h1>
    <p class="text-body text-gray-600 mt-2">All generated schedules</p>
</div>

<div class="card p-6">
    <div class="overflow-x-auto">
        <table class="table-minimal w-full">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>PERIOD</th>
                    <th>STATUS</th>
                    <th>EMPLOYEES</th>
                    <th>TOTAL SALARY</th>
                    <th>GA FITNESS</th>
                    <th>RF SCORE</th>
                    <th>CREATED</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schedules as $schedule)
                <tr>
                    <td class="font-mono text-caption">{{ $schedule->id }}</td>
                    <td class="font-mono text-caption">
                        {{ $schedule->start_date->format('M d') }} - {{ $schedule->end_date->format('M d, Y') }}
                        <span class="text-gray-400">({{ $schedule->days }}d)</span>
                    </td>
                    <td>
                        <span class="badge badge-{{ $schedule->status === 'published' ? 'success' : ($schedule->status === 'archived' ? 'secondary' : 'warning') }}">
                            {{ strtoupper($schedule->status) }}
                        </span>
                    </td>
                    <td class="font-mono">
                        @if($schedule->selectedCandidate)
                            {{ $schedule->selectedCandidate->active_employees }}
                        @elseif($schedule->status === 'draft' && $schedule->candidates->isNotEmpty())
                            {{ $schedule->candidates->first()->active_employees }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="font-mono">
                        @if($schedule->selectedCandidate)
                            ${{ number_format($schedule->selectedCandidate->total_salary / 1000, 1) }}K
                        @elseif($schedule->status === 'draft' && $schedule->candidates->isNotEmpty())
                            ${{ number_format($schedule->candidates->first()->total_salary / 1000, 1) }}K
                        @else
                            -
                        @endif
                    </td>
                    <td class="font-mono">
                        @if($schedule->selectedCandidate)
                            {{ number_format($schedule->selectedCandidate->ga_fitness, 1) }}
                        @elseif($schedule->status === 'draft' && $schedule->candidates->isNotEmpty())
                            {{ number_format($schedule->candidates->first()->ga_fitness, 1) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="font-mono text-green-600">
                        @if($schedule->selectedCandidate && $schedule->selectedCandidate->rf_profit_score)
                            {{ number_format($schedule->selectedCandidate->rf_profit_score, 1) }}%
                        @elseif($schedule->status === 'draft' && $schedule->candidates->isNotEmpty() && $schedule->candidates->first()->rf_profit_score)
                            {{ number_format($schedule->candidates->first()->rf_profit_score, 1) }}%
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-caption text-gray-500">{{ $schedule->created_at->format('M d, H:i') }}</td>
                    <td>
                        <div class="flex items-center space-x-2">
                            <a href="{{ route('manager.schedules.show', $schedule) }}" class="text-blue-600 hover:underline text-caption">VIEW</a>
                            @if($schedule->status !== 'archived')
                            <form method="POST" action="{{ route('manager.schedules.destroy', $schedule) }}" class="inline" onsubmit="return confirm('Archive?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-caption">ARCHIVE</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-gray-400 py-8">No schedules generated yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $schedules->links() }}</div>
@endsection
