<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with('department')->active();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('employee_code', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('education')) {
            $query->where('education', $request->education);
        }

        if ($request->filled('job_level')) {
            $query->where('job_level', $request->job_level);
        }

        if ($request->filled('cluster')) {
            $query->where('cluster_label', $request->cluster);
        }

        $employees = $query->latest()->paginate(20);
        $departments = Department::where('is_active', true)->get();

        return view('manager.employees.index', compact('employees', 'departments'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        return view('manager.employees.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'age' => 'required|integer|min:16|max:100',
            'department_id' => 'required|exists:departments,id',
            'location' => 'nullable|string|max:255',
            'education' => 'required|in:UG,PG',
            'recruitment_type' => 'nullable|string|max:255',
            'job_level' => 'required|integer|min:1|max:5',
            'rating' => 'required|integer|min:1|max:5',
            'onsite' => 'boolean',
            'awards' => 'integer|min:0',
            'certifications' => 'integer|min:0',
            'salary' => 'required|numeric|min:0',
            'satisfied' => 'boolean',
        ]);

        $validated['is_senior'] = $validated['education'] === 'PG';

        Employee::create($validated);

        return redirect()->route('manager.employees.index')
            ->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee)
    {
        $departments = Department::where('is_active', true)->get();
        return view('manager.employees.edit', compact('employee', 'departments'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'age' => 'required|integer|min:16|max:100',
            'department_id' => 'required|exists:departments,id',
            'location' => 'nullable|string|max:255',
            'education' => 'required|in:UG,PG',
            'recruitment_type' => 'nullable|string|max:255',
            'job_level' => 'required|integer|min:1|max:5',
            'rating' => 'required|integer|min:1|max:5',
            'onsite' => 'boolean',
            'awards' => 'integer|min:0',
            'certifications' => 'integer|min:0',
            'salary' => 'required|numeric|min:0',
            'satisfied' => 'boolean',
        ]);

        $validated['is_senior'] = $validated['education'] === 'PG';

        $employee->update($validated);

        return redirect()->route('manager.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $employee->update(['is_active' => false]);
        $employee->delete();

        return redirect()->route('manager.employees.index')
            ->with('success', 'Employee deactivated successfully.');
    }

    public function showImport()
    {
        return view('manager.employees.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $content = file_get_contents($file->getRealPath());
        $rows = array_map('str_getcsv', explode("\n", $content));
        $header = array_shift($rows);

        $imported = 0;
        $skipped = 0;
        
        // Get max employee number from existing employees
        $maxEmployee = Employee::where('name', 'like', 'Employee%')
            ->get()
            ->map(function($emp) {
                preg_match('/Employee(\d+)/', $emp->name, $matches);
                return isset($matches[1]) ? (int)$matches[1] : 0;
            })
            ->max();
        
        $counter = $maxEmployee ? $maxEmployee + 1 : 1;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Skip empty rows
                if (empty($row) || empty($row[0]) || trim($row[0]) === '') {
                    $skipped++;
                    continue;
                }
                
                if (count($row) !== count($header)) {
                    $skipped++;
                    continue;
                }

                $data = array_combine($header, $row);

                // Skip if emp_id is empty
                if (empty(trim($data['emp_id']))) {
                    $skipped++;
                    continue;
                }

                // Find or create department by name only
                $department = Department::firstOrCreate(
                    ['name' => trim($data['Dept'])],
                    [
                        'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['Dept']), 0, 4)) . rand(10, 99),
                        'is_active' => true
                    ]
                );

                // Generate sequential employee name
                $employeeName = "Employee{$counter}";

                $employee = Employee::updateOrCreate(
                    ['employee_code' => trim($data['emp_id'])],
                    [
                        'name' => $employeeName,
                        'age' => (int)$data['age'],
                        'department_id' => $department->id,
                        'location' => trim($data['location'] ?? ''),
                        'education' => strtoupper(trim($data['education'])),
                        'recruitment_type' => trim($data['recruitment_type'] ?? ''),
                        'job_level' => (int)$data['job_level'],
                        'rating' => (int)$data['rating'],
                        'onsite' => (bool)($data['onsite'] ?? 0),
                        'awards' => (int)($data['awards'] ?? 0),
                        'certifications' => (int)($data['certifications'] ?? 0),
                        'salary' => (float)$data['salary'],
                        'satisfied' => (bool)($data['satisfied'] ?? 0),
                        'is_senior' => strtoupper(trim($data['education'])) === 'PG',
                        'is_active' => true,
                    ]
                );

                // Auto-create user account for employee
                User::updateOrCreate(
                    ['email' => strtolower($employeeName).'@shiftly.com'],
                    [
                        'name' => $employeeName,
                        'password' => bcrypt('password'),
                        'role' => 'employee',
                        'employee_id' => $employee->id,
                    ]
                );

                $imported++;
                $counter++;
            }

            DB::commit();

            $message = "Successfully imported {$imported} employees";
            if ($skipped > 0) {
                $message .= " ({$skipped} rows skipped)";
            }

            return redirect()->route('manager.employees.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }
    }
}
