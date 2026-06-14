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
            'is_active' => 'boolean',
        ]);

        $department->update($validated);

        return redirect()->route('manager.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $department->update(['is_active' => false]);
        $department->delete();

        return redirect()->route('manager.departments.index')
            ->with('success', 'Department deactivated successfully.');
    }
}
