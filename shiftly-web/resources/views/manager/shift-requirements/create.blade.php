@extends('layouts.manager')

@section('title', 'Add Shift Requirement')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Add Shift Requirement</h1>
</div>

<div class="card p-6 mb-6">
    <form method="POST" action="{{ route('manager.shift-requirements.store') }}">
        @csrf
        <div class="mb-4">
            <label>Department *</label>
            <select name="department_id" required class="w-full text-body">
                <option value="">Select Department</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label>Shift *</label>
            <select name="shift" required class="w-full text-body">
                <option value="">Select Shift</option>
                @foreach($shifts as $shift)
                    <option value="{{ $shift }}" {{ old('shift') === $shift ? 'selected' : '' }}>{{ $shift }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label>Required Staff *</label>
            <input type="number" name="required_staff" value="{{ old('required_staff', 0) }}" required min="0" class="w-full text-body">
        </div>

        <div class="mb-4">
            <label>Required Senior *</label>
            <input type="number" name="required_senior" value="{{ old('required_senior', 0) }}" required min="0" class="w-full text-body">
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('manager.shift-requirements.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Requirement</button>
        </div>
    </form>
</div>
@endsection
