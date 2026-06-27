@extends('layouts.manager')

@section('title', 'Edit Department')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Edit Department</h1>
</div>

<div class="card p-6 mb-6">
    <form method="POST" action="{{ route('manager.departments.update', $department) }}">
        @csrf @method('PUT')
        <div class="mb-4">
            <label>Department Name *</label>
            <input type="text" name="name" value="{{ old('name', $department->name) }}" required class="w-full text-body">
        </div>

        <div class="mb-4">
            <label>Department Code *</label>
            <input type="text" name="code" value="{{ old('code', $department->code) }}" required maxlength="32" class="w-full text-body">
        </div>

        <div class="mb-4">
            <label>Status</label>
            <div class="flex items-center h-10">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $department->is_active) ? 'checked' : '' }} style="width: auto; margin-right: 8px;">
                <span class="text-body text-gray-700">Active</span>
            </div>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('manager.departments.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Department</button>
        </div>
    </form>
</div>
@endsection
