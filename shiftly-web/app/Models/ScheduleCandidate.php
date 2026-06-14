<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kandidat jadwal hasil GA dan evaluasi RF.
 *
 * Manager membandingkan beberapa candidate, lalu satu candidate dapat ditandai
 * selected untuk dipublish ke employee.
 */
class ScheduleCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_run_id',
        'candidate_code',
        'ga_fitness',
        'rf_profit_score',
        'total_salary',
        'active_employees',
        'total_assignments',
        'cluster_balance',
        'hard_violation_count',
        'soft_violation_count',
        'consecutive_shift_violations',
        'one_shift_per_day_violations',
        'weekly_day_off_violations',
        'shift_counts',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ga_fitness' => 'decimal:4',
            'rf_profit_score' => 'decimal:4',
            'total_salary' => 'decimal:2',
            'active_employees' => 'integer',
            'total_assignments' => 'integer',
            'cluster_balance' => 'decimal:4',
            'hard_violation_count' => 'integer',
            'soft_violation_count' => 'integer',
            'consecutive_shift_violations' => 'integer',
            'one_shift_per_day_violations' => 'integer',
            'weekly_day_off_violations' => 'integer',
            'shift_counts' => 'array',
        ];
    }

    public function scheduleRun(): BelongsTo
    {
        return $this->belongsTo(ScheduleRun::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    public function constraintReports(): HasMany
    {
        return $this->hasMany(ScheduleConstraintReport::class);
    }
}
