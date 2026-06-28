@extends('layouts.manager')

@section('title', 'Add Shift Requirement')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Add Shift Requirement</h1>
</div>

@if($errors->any())
<div class="card p-4 mb-6" style="background-color: #FEE; border-color: #FCC;">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
            <div class="font-semibold text-red-800 mb-1">Error</div>
            @foreach($errors->all() as $error)
                <p class="text-caption text-red-700">{{ $error }}</p>
            @endforeach
        </div>
    </div>
</div>
@endif

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
