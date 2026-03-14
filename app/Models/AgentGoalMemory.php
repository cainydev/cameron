<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentGoalMemoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentGoalMemory extends Model
{
    /** @use HasFactory<AgentGoalMemoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'agent_goal_id',
        'insight',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AgentGoal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(AgentGoal::class);
    }
}
