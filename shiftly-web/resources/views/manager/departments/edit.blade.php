@extends('layouts.app')

@section('title', 'Edit Department')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Edit Department</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('manager.departments.update', $department) }}">
        @csrf @method('PUT')
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Department Name *</label>
            <input type="text" name="name" value="{{ old('name', $department->name) }}" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Department Code *</label>
            <input type="text" name="code" value="{{ old('code', $department->code) }}" required maxlength="32" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4 flex items-center">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $department->is_active) ? 'checked' : '' }} class="mr-2">
            <label class="text-sm font-medium text-gray-700">Active</label>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('manager.departments.index') }}" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Update Department</button>
        </div>
    </form>
</div>
@endsection
