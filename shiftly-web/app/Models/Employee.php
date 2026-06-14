<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Profil employee/perawat dari CSV atau input manual manager.
 *
 * employee_code bisa berasal dari emp_id dataset atau dibuat otomatis saat
 * manager menambah employee manual. is_senior mengikuti education PG dan
 * cluster_label diisi dari endpoint K-Means.
 */
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_code',
        'name',
        'age',
        'department_id',
        'location',
        'education',
        'recruitment_type',
        'job_level',
        'rating',
        'onsite',
        'awards',
        'certifications',
        'salary',
        'satisfied',
        'cluster_label',
        'clustered_at',
        'is_senior',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'department_id' => 'integer',
            'job_level' => 'integer',
            'rating' => 'integer',
            'onsite' => 'boolean',
            'awards' => 'integer',
            'certifications' => 'integer',
            'salary' => 'decimal:2',
            'satisfied' => 'boolean',
            'cluster_label' => 'integer',
            'clustered_at' => 'datetime',
            'is_senior' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee) {
            if (blank($employee->employee_code)) {
                $employee->employee_code = static::generateEmployeeCode();
            }
        });
    }

    public static function generateEmployeeCode(): string
    {
        do {
            $code = 'EMP'.now()->format('ymd').strtoupper(Str::random(6));
        } while (static::where('employee_code', $code)->exists());

        return $code;
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDepartment(Builder $query, int|string|null $department): Builder
    {
        if (! $department) {
            return $query;
        }

        if (is_numeric($department)) {
            return $query->where('department_id', $department);
        }

        return $query->whereHas('department', fn (Builder $query) => $query->where('name', $department));
    }

    public function scopeForEducation(Builder $query, ?string $education): Builder
    {
        return $education ? $query->where('education', $education) : $query;
    }

    public function scopeForJobLevel(Builder $query, ?int $jobLevel): Builder
    {
        return $jobLevel ? $query->where('job_level', $jobLevel) : $query;
    }

    public function scopeForCluster(Builder $query, ?int $clusterLabel): Builder
    {
        return $clusterLabel !== null ? $query->where('cluster_label', $clusterLabel) : $query;
    }

    public function scopeSenior(Builder $query): Builder
    {
        return $query->where('is_senior', true);
    }
}
