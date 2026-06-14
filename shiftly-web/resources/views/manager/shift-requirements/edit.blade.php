@extends('layouts.app')

@section('title', 'Edit Shift Requirement')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Edit Shift Requirement</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('manager.shift-requirements.update', $shiftRequirement) }}">
        @csrf @method('PUT')
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <input type="text" value="{{ $shiftRequirement->department->name }}" disabled class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Shift</label>
            <input type="text" value="{{ $shiftRequirement->shift }}" disabled class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Required Staff *</label>
            <input type="number" name="required_staff" value="{{ old('required_staff', $shiftRequirement->required_staff) }}" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Required Senior *</label>
            <input type="number" name="required_senior" value="{{ old('required_senior', $shiftRequirement->required_senior) }}" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4 flex items-center">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $shiftRequirement->is_active) ? 'checked' : '' }} class="mr-2">
            <label class="text-sm font-medium text-gray-700">Active</label>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('manager.shift-requirements.index') }}" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Update Requirement</button>
        </div>
    </form>
</div>
@endsection
