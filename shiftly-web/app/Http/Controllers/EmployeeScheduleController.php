<?php

namespace App\Http\Controllers;

use App\Models\ScheduleEntry;
use Illuminate\Http\Request;

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
