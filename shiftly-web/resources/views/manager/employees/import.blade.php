@extends('layouts.app')

@section('title', 'Import Employees')

@section('content')
<div class="mb-8">
    <h1 class="text-display">Import Employees</h1>
    <p class="text-caption mt-2">Bulk upload employee data from CSV file</p>
</div>

<!-- Info Card -->
<div class="card p-5 mb-6" style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border-color: #FCD34D;">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
            <div class="font-semibold text-amber-900 mb-2">Auto-Generated User Accounts</div>
            <div class="text-caption text-amber-800">
                <p class="mb-2">System will automatically:</p>
                <ul class="list-disc list-inside ml-2 space-y-1">
                    <li>Generate unique names: <strong class="mono">Employee1, Employee2, Employee3, ...</strong></li>
                    <li>Create login accounts for each employee</li>
                    <li>Email format: <strong class="mono">employee1@shiftly.com, employee2@shiftly.com, ...</strong></li>
                    <li>Default password: <strong class="mono">password</strong></li>
                </ul>
                <p class="mt-2">Employees can login to view their shift schedules.</p>
            </div>
        </div>
    </div>
</div>

<!-- CSV Format Card -->
<div class="card p-6 mb-6">
    <h2 class="text-title mb-4">CSV Format Required</h2>
    <p class="text-body text-ink-mute mb-4">Your CSV file must have the following columns:</p>
    
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
        <div class="text-tiny text-ink-mute mb-2">HEADER ROW</div>
        <div class="mono text-caption text-ink overflow-x-auto">
            emp_id,age,Dept,location,education,recruitment_type,job_level,rating,onsite,awards,certifications,salary,satisfied
        </div>
    </div>
    
    <div class="text-tiny text-ink-mute mb-2">EXAMPLE DATA</div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mono text-caption text-ink-mute overflow-x-auto">
        HR8270,28,Emergency Physician,Suburb,PG,Referral,5,2,0,1,0,86750,1<br>
        TECH1860,50,Nurse Practitioner,Suburb,PG,Walk-in,3,5,1,2,1,42419,0
    </div>
</div>

<!-- Upload Form Card -->
<div class="card p-6">
    <form method="POST" action="{{ route('manager.employees.import.process') }}" enctype="multipart/form-data">
        @csrf
        
        <div class="mb-6">
            <label class="text-body font-semibold mb-2 block">Select CSV File</label>
            <div class="relative">
                <input type="file" name="csv_file" accept=".csv,.txt" required 
                       class="w-full" 
                       onchange="document.getElementById('filename').textContent = this.files[0]?.name || 'No file chosen'">
            </div>
            <div class="flex items-center gap-2 mt-2">
                <svg class="w-4 h-4 text-ink-mute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span id="filename" class="text-caption mono">No file chosen</span>
            </div>
            <p class="text-caption mt-2">Maximum file size: 10MB</p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('manager.employees.index') }}" class="btn btn-secondary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Cancel</span>
            </a>
            <button type="submit" class="btn btn-success">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span>Import Employees</span>
            </button>
        </div>
    </form>
</div>
@endsection
