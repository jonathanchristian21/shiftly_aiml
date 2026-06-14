<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ScheduleRun;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_employees' => Employee::active()->count(),
            'clustered_employees' => Employee::active()->whereNotNull('cluster_label')->count(),
            'total_schedules' => ScheduleRun::count(),
            'published_schedules' => ScheduleRun::where('status', 'published')->count(),
        ];

        $recentEmployees = Employee::with('department')
            ->active()
            ->latest()
            ->limit(10)
            ->get();

        return view('manager.dashboard', compact('stats', 'recentEmployees'));
    }
}
