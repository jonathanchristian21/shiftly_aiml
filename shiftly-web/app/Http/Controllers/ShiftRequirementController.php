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
            ->paginate(20);
        
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
            'is_active' => 'boolean',
        ]);

        $shiftRequirement->update($validated);

        return redirect()->route('manager.shift-requirements.index')
            ->with('success', 'Shift requirement updated successfully.');
    }

    public function destroy(DepartmentShiftRequirement $shiftRequirement)
    {
        $shiftRequirement->delete();

        return redirect()->route('manager.shift-requirements.index')
            ->with('success', 'Shift requirement deleted successfully.');
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
