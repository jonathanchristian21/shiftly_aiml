@extends('layouts.app')

@section('title', 'Edit Employee')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Edit Employee</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('manager.employees.update', $employee) }}">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Employee Code</label>
                <input type="text" value="{{ $employee->employee_code }}" disabled class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                <input type="text" name="name" value="{{ old('name', $employee->name) }}" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Age *</label>
                <input type="number" name="age" value="{{ old('age', $employee->age) }}" required min="16" max="100" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
                <select name="department_id" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', $employee->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                <input type="text" name="location" value="{{ old('location', $employee->location) }}" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Education *</label>
                <select name="education" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="UG" {{ old('education', $employee->education) === 'UG' ? 'selected' : '' }}>UG</option>
                    <option value="PG" {{ old('education', $employee->education) === 'PG' ? 'selected' : '' }}>PG</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Recruitment Type</label>
                <input type="text" name="recruitment_type" value="{{ old('recruitment_type', $employee->recruitment_type) }}" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Job Level *</label>
                <select name="job_level" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" {{ old('job_level', $employee->job_level) == $i ? 'selected' : '' }}>Level {{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Rating *</label>
                <select name="rating" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" {{ old('rating', $employee->rating) == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Salary *</label>
                <input type="number" name="salary" value="{{ old('salary', $employee->salary) }}" required min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Certifications</label>
                <input type="number" name="certifications" value="{{ old('certifications', $employee->certifications) }}" min="0" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Awards</label>
                <input type="number" name="awards" value="{{ old('awards', $employee->awards) }}" min="0" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="onsite" value="1" {{ old('onsite', $employee->onsite) ? 'checked' : '' }} class="mr-2">
                <label class="text-sm font-medium text-gray-700">Onsite</label>
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="satisfied" value="1" {{ old('satisfied', $employee->satisfied) ? 'checked' : '' }} class="mr-2">
                <label class="text-sm font-medium text-gray-700">Satisfied</label>
            </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
            <a href="{{ route('manager.employees.index') }}" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Update Employee</button>
        </div>
    </form>
</div>
@endsection
