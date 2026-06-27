@extends('layouts.manager')

@section('title', 'Add Employee')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Add New Employee</h1>
</div>

<div class="card p-6 mb-6">
    <form method="POST" action="{{ route('manager.employees.store') }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label>Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full text-body">
            </div>
            <div>
                <label>Age *</label>
                <input type="number" name="age" value="{{ old('age', 25) }}" required min="16" max="100" class="w-full text-body">
            </div>
            <div>
                <label>Department *</label>
                <select name="department_id" required class="w-full text-body">
                    <option value="">Select Department</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Location</label>
                <select name="location" class="w-full text-body">
                    <option value="" {{ old('location') === '' ? 'selected' : '' }}>-- Select Location --</option>
                    <option value="suburb" {{ old('location') === 'suburb' ? 'selected' : '' }}>Suburb</option>
                    <option value="city" {{ old('location') === 'city' ? 'selected' : '' }}>City</option>
                </select>
            </div>
            <div>
                <label>Education *</label>
                <select name="education" required class="w-full text-body">
                    <option value="UG" {{ old('education') === 'UG' ? 'selected' : '' }}>UG (Undergraduate)</option>
                    <option value="PG" {{ old('education') === 'PG' ? 'selected' : '' }}>PG (Postgraduate)</option>
                </select>
            </div>
            <div>
                <label>Recruitment Type</label>
                <select name="recruitment_type" class="w-full text-body">
                    <option value="" {{ old('recruitment_type') === '' ? 'selected' : '' }}>-- Select Type --</option>
                    <option value="on-campus" {{ old('recruitment_type') === 'on-campus' ? 'selected' : '' }}>On-Campus</option>
                    <option value="recruitment agency" {{ old('recruitment_type') === 'recruitment agency' ? 'selected' : '' }}>Recruitment Agency</option>
                    <option value="referral" {{ old('recruitment_type') === 'referral' ? 'selected' : '' }}>Referral</option>
                    <option value="walk-in" {{ old('recruitment_type') === 'walk-in' ? 'selected' : '' }}>Walk-In</option>
                </select>
            </div>
            <div>
                <label>Job Level *</label>
                <select name="job_level" required class="w-full text-body">
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" {{ old('job_level') == $i ? 'selected' : '' }}>Level {{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label>Rating *</label>
                <select name="rating" required class="w-full text-body">
                    @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" {{ old('rating', 3) == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label>Salary *</label>
                <input type="number" name="salary" value="{{ old('salary', 30000) }}" required min="0" step="0.01" class="w-full text-body">
            </div>
            <div>
                <label>Certifications</label>
                <input type="number" name="certifications" value="{{ old('certifications', 0) }}" min="0" class="w-full text-body">
            </div>
            <div>
                <label>Awards</label>
                <input type="number" name="awards" value="{{ old('awards', 0) }}" min="0" class="w-full text-body">
            </div>
            {{-- onsite is used internally by the AI model; default value is passed via hidden field --}}
            <input type="hidden" name="onsite" value="{{ old('onsite', 0) }}">
            <div>
                <label>Job Satisfaction</label>
                <div class="flex items-center h-10">
                    <input type="checkbox" name="satisfied" value="1" {{ old('satisfied', true) ? 'checked' : '' }} style="width: auto; margin-right: 8px;">
                    <span class="text-body text-gray-700">Satisfied with current role</span>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
            <a href="{{ route('manager.employees.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Employee</button>
        </div>
    </form>
</div>
@endsection
