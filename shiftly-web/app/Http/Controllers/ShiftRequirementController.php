<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentShiftRequirement;
use Illuminate\Http\Request;

class ShiftRequirementController extends Controller
{
    public function index()
    {
        $requirements = DepartmentShiftRequirement::with('department')
            ->orderBy('department_id')
            ->orderBy('shift')
            ->get();
        
        $departments = Department::where('is_active', true)
            ->withCount('employees')
            ->get();
        
        return view('manager.shift-requirements.index', compact('requirements', 'departments'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        $shifts = ['Pagi', 'Sore', 'Malam'];
        
        return view('manager.shift-requirements.create', compact('departments', 'shifts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'shift' => 'required|in:Pagi,Sore,Malam',
            'required_staff' => 'required|integer|min:0',
            'required_senior' => 'required|integer|min:0',
        ]);

        // Check if combination already exists
        $exists = DepartmentShiftRequirement::where('department_id', $validated['department_id'])
            ->where('shift', $validated['shift'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['shift' => 'This department already has a requirement for this shift. Please edit the existing one instead.']);
        }

        $validated['is_active'] = true;

        DepartmentShiftRequirement::create($validated);

        return redirect()->route('manager.shift-requirements.index')
            ->with('success', 'Shift requirement created successfully.');
    }

    public function edit(DepartmentShiftRequirement $shiftRequirement)
    {
        $departments = Department::where('is_active', true)->get();
        $shifts = ['Pagi', 'Sore', 'Malam'];
        
        return view('manager.shift-requirements.edit', compact('shiftRequirement', 'departments', 'shifts'));
    }

    public function update(Request $request, DepartmentShiftRequirement $shiftRequirement)
    {
        $validated = $request->validate([
            'required_staff' => 'required|integer|min:0',
            'required_senior' => 'required|integer|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $shiftRequirement->update($validated);

        return redirect()->route('manager.shift-requirements.index')
            ->with('success', 'Shift requirement updated successfully.');
    }

    public function destroy(Request $request, DepartmentShiftRequirement $shiftRequirement = null)
    {
        // Bulk delete
        if ($request->has('ids')) {
            $ids = $request->input('ids', []);
            $count = DepartmentShiftRequirement::whereIn('id', $ids)->delete();
            return redirect()->route('manager.shift-requirements.index')
                ->with('success', "Successfully deleted {$count} shift requirements.");
        }

        // Single delete
        if ($shiftRequirement) {
            $shiftRequirement->delete();
            return redirect()->route('manager.shift-requirements.index')
                ->with('success', 'Shift requirement deleted successfully.');
        }

        return redirect()->route('manager.shift-requirements.index')
            ->withErrors(['error' => 'No requirements selected for deletion.']);
    }

    public function activate(DepartmentShiftRequirement $shiftRequirement)
    {
        $shiftRequirement->update(['is_active' => true]);
        return redirect()->route('manager.shift-requirements.index')
            ->with('success', 'Shift requirement activated successfully.');
    }

    public function deactivate(DepartmentShiftRequirement $shiftRequirement)
    {
        $shiftRequirement->update(['is_active' => false]);
        return redirect()->route('manager.shift-requirements.index')
            ->with('success', 'Shift requirement deactivated successfully.');
    }

    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:department_shift_requirements,id',
        ]);

        $ids = $request->input('ids', []);
        $count = DepartmentShiftRequirement::whereIn('id', $ids)->update(['is_active' => true]);

        return redirect()->route('manager.shift-requirements.index')
            ->with('success', "Successfully activated {$count} shift requirements.");
    }

    public function bulkDeactivate(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:department_shift_requirements,id',
        ]);

        $ids = $request->input('ids', []);
        $count = DepartmentShiftRequirement::whereIn('id', $ids)->update(['is_active' => false]);

        return redirect()->route('manager.shift-requirements.index')
            ->with('success', "Successfully deactivated {$count} shift requirements.");
    }

    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'departments' => 'required|array|min:1',
            'departments.*' => 'exists:departments,id',
            'shifts' => 'required|array',
            'shifts.*.enabled' => 'boolean',
            'shifts.*.required_staff' => 'required|integer|min:0',
            'shifts.*.required_senior' => 'required|integer|min:0',
        ]);

        $created = 0;
        $shifts = ['Pagi', 'Sore', 'Malam'];

        foreach ($validated['departments'] as $deptId) {
            foreach ($shifts as $index => $shiftName) {
                $shiftData = $validated['shifts'][$index] ?? null;
                
                if ($shiftData && ($shiftData['enabled'] ?? false)) {
                    DepartmentShiftRequirement::updateOrCreate(
                        [
                            'department_id' => $deptId,
                            'shift' => $shiftName,
                        ],
                        [
                            'required_staff' => $shiftData['required_staff'],
                            'required_senior' => $shiftData['required_senior'],
                            'is_active' => true,
                        ]
                    );
                    $created++;
                }
            }
        }

        return redirect()->route('manager.shift-requirements.index')
            ->with('success', "Successfully created/updated {$created} shift requirements.");
    }
}
