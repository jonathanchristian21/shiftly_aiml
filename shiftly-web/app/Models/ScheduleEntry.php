<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail assignment per employee per tanggal.
 *
 * Data inilah yang dibaca halaman "Jadwal Saya" milik employee setelah
 * schedule candidate dipublish.
 */
class ScheduleEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_candidate_id',
        'employee_id',
        'department_id',
        'shift_date',
        'shift',
        'cluster_label',
        'is_senior_snapshot',
        'salary_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'shift_date' => 'date',
            'cluster_label' => 'integer',
            'is_senior_snapshot' => 'boolean',
            'salary_snapshot' => 'decimal:2',
        ];
    }

    public function scheduleCandidate(): BelongsTo
    {
        return $this->belongsTo(ScheduleCandidate::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
