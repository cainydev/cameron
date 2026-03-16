<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentGoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentGoal extends Model
{
    /** @use HasFactory<AgentGoalFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'name',
        'initial_context',
        'sensor_tool_class',
        'sensor_arguments',
        'conditions',
        'is_active',
        'expires_at',
        'is_one_off',
        'check_frequency_minutes',
        'last_checked_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sensor_arguments' => 'array',
            'conditions' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'is_one_off' => 'boolean',
            'check_frequency_minutes' => 'integer',
            'last_checked_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Scope to goals that are due for their next check.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDueForCheck(Builder $query): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        return $query->where(function ($q) use ($driver) {
            $q->whereNull('last_checked_at')
                ->orWhere(function ($q2) use ($driver) {
                    if ($driver === 'sqlite') {
                        $q2->whereRaw("datetime(last_checked_at, '+' || check_frequency_minutes || ' minutes') <= datetime('now')");
                    } else {
                        $q2->whereRaw('DATE_ADD(last_checked_at, INTERVAL check_frequency_minutes MINUTE) <= NOW()');
                    }
                });
        });
    }

    /**
     * Whether this goal is due to be checked based on its frequency and last check time.
     */
    public function isDueForCheck(): bool
    {
        if ($this->last_checked_at === null) {
            return true;
        }

        return $this->last_checked_at->addMinutes($this->check_frequency_minutes)->isPast();
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<AgentTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(AgentTask::class, 'goal_id');
    }

    /**
     * @return HasMany<AgentGoalMemory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(AgentGoalMemory::class);
    }
}
