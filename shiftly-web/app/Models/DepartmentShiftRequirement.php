<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kebutuhan minimum staff dan senior untuk tiap department dan shift.
 *
 * Data ini menjadi constraint utama yang dikirim Laravel ke FastAPI saat
 * manager generate jadwal.
 */
class DepartmentShiftRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'shift',
        'required_staff',
        'required_senior',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required_staff' => 'integer',
            'required_senior' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
