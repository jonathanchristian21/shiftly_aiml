<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ringkasan constraint per department, tanggal, dan shift.
 *
 * Tabel ini menyimpan angka required vs actual agar manager bisa melihat
 * apakah kandidat jadwal memenuhi kebutuhan staff dan senior.
 */
class ScheduleConstraintReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_candidate_id',
        'department_id',
        'shift_date',
        'shift',
        'required_staff',
        'actual_staff',
        'required_senior',
        'actual_senior',
        'has_hard_violation',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'department_id' => 'integer',
            'required_staff' => 'integer',
            'actual_staff' => 'integer',
            'required_senior' => 'integer',
            'actual_senior' => 'integer',
            'has_hard_violation' => 'boolean',
        ];
    }

    public function scheduleCandidate(): BelongsTo
    {
        return $this->belongsTo(ScheduleCandidate::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
