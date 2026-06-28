<?php

namespace App\Http\Controllers;

use App\Models\ScheduleEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeScheduleController extends Controller
{
    public function schedule(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return view('employee.schedule')->with('error', 'No employee profile found.');
        }

        $schedules = ScheduleEntry::with(['scheduleCandidate.scheduleRun'])
            ->where('employee_id', $employee->id)
            ->whereHas('scheduleCandidate', function($q) {
                $q->where('status', 'selected')
                    ->whereHas('scheduleRun', fn($qr) => $qr->where('status', 'published'));
            })
            ->whereHas('department', function($q) {
                $q->where('is_active', true);
            })
            ->whereExists(function($q) {
                $q->select(DB::raw(1))
                  ->from('department_shift_requirements')
                  ->whereColumn('department_shift_requirements.department_id', 'schedule_entries.department_id')
                  ->whereColumn('department_shift_requirements.shift', 'schedule_entries.shift')
                  ->where('department_shift_requirements.is_active', true);
            })
            ->orderBy('shift_date')
            ->paginate(30);

        return view('employee.schedule', compact('employee', 'schedules'));
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        return view('employee.profile', compact('user', 'employee'));
    }
}
