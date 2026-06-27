@extends('layouts.manager')

@section('title', 'Edit Shift Requirement')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Edit Shift Requirement</h1>
</div>

<div class="card p-6 mb-6">
    <form method="POST" action="{{ route('manager.shift-requirements.update', $shiftRequirement) }}">
        @csrf @method('PUT')
        <div class="mb-4">
            <label>Department</label>
            <input type="text" value="{{ $shiftRequirement->department->name }}" disabled class="w-full text-body bg-gray-50">
        </div>

        <div class="mb-4">
            <label>Shift</label>
            <input type="text" value="{{ $shiftRequirement->shift }}" disabled class="w-full text-body bg-gray-50">
        </div>

        <div class="mb-4">
            <label>Required Staff *</label>
            <input type="number" name="required_staff" value="{{ old('required_staff', $shiftRequirement->required_staff) }}" required min="0" class="w-full text-body">
        </div>

        <div class="mb-4">
            <label>Required Senior *</label>
            <input type="number" name="required_senior" value="{{ old('required_senior', $shiftRequirement->required_senior) }}" required min="0" class="w-full text-body">
        </div>

        <div class="mb-4 flex items-center">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $shiftRequirement->is_active) ? 'checked' : '' }} class="mr-2">
            <label class="text-sm font-medium text-gray-700">Active</label>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('manager.shift-requirements.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Requirement</button>
        </div>
    </form>
</div>
@endsection
