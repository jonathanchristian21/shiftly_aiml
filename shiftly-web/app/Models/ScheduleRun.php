<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satu sesi generate jadwal.
 *
 * Menyimpan range tanggal, filter pool, snapshot requirement, parameter GA,
 * dan status draft/published/archived.
 */
class ScheduleRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'manager_id',
        'name',
        'start_date',
        'end_date',
        'days',
        'filters',
        'requirements_snapshot',
        'ga_parameters',
        'generated_at',
        'status',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'filters' => 'array',
            'requirements_snapshot' => 'array',
            'ga_parameters' => 'array',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ScheduleCandidate::class);
    }

    public function selectedCandidate(): HasOne
    {
        return $this->hasOne(ScheduleCandidate::class)->where('status', 'selected');
    }
}
