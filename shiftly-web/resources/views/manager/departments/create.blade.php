@extends('layouts.manager')

@section('title', 'Add Department')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Add New Department</h1>
</div>

<div class="card p-6 mb-6">
    <form method="POST" action="{{ route('manager.departments.store') }}">
        @csrf
        <div class="mb-4">
            <label>Department Name *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full text-body">
        </div>

        <div class="mb-4">
            <label>Department Code *</label>
            <input type="text" name="code" value="{{ old('code') }}" required maxlength="32" class="w-full text-body">
        </div>

        <div class="flex justify-end space-x-3">
            <a href="{{ route('manager.departments.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Department</button>
        </div>
    </form>
</div>
@endsection
