@extends('layouts.app')

@section('title', 'Add Shift Requirement')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Add Shift Requirement</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('manager.shift-requirements.store') }}">
        @csrf
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
            <select name="department_id" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                <option value="">Select Department</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Shift *</label>
            <select name="shift" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                <option value="">Select Shift</option>
                @foreach($shifts as $shift)
                    <option value="{{ $shift }}" {{ old('shift') === $shift ? 'selected' : '' }}>{{ $shift }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Required Staff *</label>
            <input type="number" name="required_staff" value="{{ old('required_staff', 0) }}" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Required Senior *</label>
            <input type="number" name="required_senior" value="{{ old('required_senior', 0) }}" required min="0" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('manager.shift-requirements.index') }}" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Create Requirement</button>
        </div>
    </form>
</div>
@endsection
