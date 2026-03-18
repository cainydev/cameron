<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgentRole;
use App\Enums\PlanStepStatus;
use Database\Factories\AgentPlanStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPlanStep extends Model
{
    /** @use HasFactory<AgentPlanStepFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'order',
        'specialist_role',
        'action',
        'depends_on_step_id',
        'status',
        'on_failure',
        'output_summary',
        'conversation_id',
        'retry_count',
        'started_at',
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
            'specialist_role' => AgentRole::class,
            'status' => PlanStepStatus::class,
            'order' => 'integer',
            'retry_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AgentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(AgentPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_step_id');
    }

    /**
     * @return BelongsTo<AgentConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id');
    }
}
