@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">My Profile</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    @if($employee)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-500">Employee Code</label>
            <p class="text-lg text-gray-900">{{ $employee->employee_code }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Name</label>
            <p class="text-lg text-gray-900">{{ $employee->name }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Email</label>
            <p class="text-lg text-gray-900">{{ $user->email }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Age</label>
            <p class="text-lg text-gray-900">{{ $employee->age }} years</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Department</label>
            <p class="text-lg text-gray-900">{{ $employee->department->name }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Education</label>
            <p class="text-lg text-gray-900">
                <span class="px-2 py-1 text-xs rounded {{ $employee->education === 'PG' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                    {{ $employee->education }}
                </span>
            </p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Job Level</label>
            <p class="text-lg text-gray-900">Level {{ $employee->job_level }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Rating</label>
            <p class="text-lg text-gray-900">{{ $employee->rating }}/5</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Certifications</label>
            <p class="text-lg text-gray-900">{{ $employee->certifications }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-500">Awards</label>
            <p class="text-lg text-gray-900">{{ $employee->awards }}</p>
        </div>
        @if($employee->cluster_label !== null)
        <div>
            <label class="block text-sm font-medium text-gray-500">Cluster</label>
            <p class="text-lg text-gray-900">
                <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">Cluster {{ $employee->cluster_label }}</span>
            </p>
        </div>
        @endif
    </div>
    @else
    <p class="text-gray-600">No employee profile found.</p>
    @endif
</div>
@endsection
