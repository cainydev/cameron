<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanStatus;
use Database\Factories\AgentPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentPlan extends Model
{
    /** @use HasFactory<AgentPlanFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'goal_id',
        'shop_id',
        'status',
        'objective',
        'working_memory',
        'conversation_id',
        'retry_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'working_memory' => 'array',
            'retry_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AgentTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class, 'task_id');
    }

    /**
     * @return BelongsTo<AgentGoal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(AgentGoal::class, 'goal_id');
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<AgentPlanStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(AgentPlanStep::class, 'plan_id')->orderBy('order');
    }

    /**
     * @return BelongsTo<AgentConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id');
    }
}
