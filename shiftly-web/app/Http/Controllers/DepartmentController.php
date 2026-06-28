<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('employees')->latest()->paginate(20);
        return view('manager.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('manager.departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments',
            'code' => 'required|string|max:32|unique:departments',
        ]);

        $validated['is_active'] = true;

        Department::create($validated);

        return redirect()->route('manager.departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function edit(Department $department)
    {
        return view('manager.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,'.$department->id,
            'code' => 'required|string|max:32|unique:departments,code,'.$department->id,
        ]);

        $validated['is_active'] = $request->has('is_active');

        $wasActive = $department->is_active;
        $department->update($validated);

        if ($wasActive && !$department->is_active) {
            $department->shiftRequirements()->update(['is_active' => false]);
        }

        return redirect()->route('manager.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        // Delete shift requirements and department
        $department->shiftRequirements()->delete();
        $department->delete(); // This will soft delete

        return redirect()->route('manager.departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    public function activate(Department $department)
    {
        $department->update(['is_active' => true]);
        $department->shiftRequirements()->update(['is_active' => true]);

        return redirect()->route('manager.departments.index')
            ->with('success', 'Department activated successfully.');
    }

    public function deactivate(Department $department)
    {
        $department->update(['is_active' => false]);
        $department->shiftRequirements()->update(['is_active' => false]);

        return redirect()->route('manager.departments.index')
            ->with('success', 'Department deactivated successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:departments,id',
        ]);

        $departments = Department::whereIn('id', $request->ids)->get();
        $count = 0;

        foreach ($departments as $department) {
            $department->shiftRequirements()->delete();
            $department->delete(); // Soft delete
            $count++;
        }

        return redirect()->route('manager.departments.index')
            ->with('success', "$count departments deleted successfully.");
    }

    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:departments,id',
        ]);

        $departments = Department::whereIn('id', $request->ids)->get();
        $count = 0;

        foreach ($departments as $department) {
            $department->shiftRequirements()->update(['is_active' => true]);
            $department->update(['is_active' => true]);
            $count++;
        }

        return redirect()->route('manager.departments.index')
            ->with('success', "$count departments activated successfully.");
    }

    public function bulkDeactivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:departments,id',
        ]);

        $departments = Department::whereIn('id', $request->ids)->get();
        $count = 0;

        foreach ($departments as $department) {
            $department->shiftRequirements()->update(['is_active' => false]);
            $department->update(['is_active' => false]);
            $count++;
        }

        return redirect()->route('manager.departments.index')
            ->with('success', "$count departments deactivated successfully.");
    }
}
