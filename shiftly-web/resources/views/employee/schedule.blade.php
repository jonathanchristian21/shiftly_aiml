@extends('layouts.app')

@section('title', 'My Schedule')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">My Schedule</h1>
</div>

@if($employee)
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-900">{{ $employee->name }}</h2>
        <p class="text-sm text-gray-600">{{ $employee->department->name }} - {{ $employee->education }}</p>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shift</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($schedules as $schedule)
            <tr>
                <td class="px-6 py-4 text-sm text-gray-900">{{ $schedule->shift_date->format('d M Y') }}</td>
                <td class="px-6 py-4 text-sm">
                    <span class="px-2 py-1 text-xs rounded 
                        {{ $schedule->shift === 'Pagi' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $schedule->shift === 'Sore' ? 'bg-orange-100 text-orange-800' : '' }}
                        {{ $schedule->shift === 'Malam' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $schedule->shift === 'Libur' ? 'bg-gray-100 text-gray-800' : '' }}">
                        {{ $schedule->shift }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">{{ $schedule->department->name }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No schedule found</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $schedules->links() }}</div>
@else
<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
    No employee profile found for your account.
</div>
@endif
@endsection
