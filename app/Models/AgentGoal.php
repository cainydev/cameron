<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentGoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'name',
        'initial_context',
        'sensor_tool_class',
        'sensor_arguments',
        'conditions',
        'is_active',
        'expires_at',
        'is_one_off',
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
            'completed_at' => 'datetime',
        ];
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
