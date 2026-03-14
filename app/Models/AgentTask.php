<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgentTaskStatus;
use Database\Factories\AgentTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentTask extends Model
{
    /** @use HasFactory<AgentTaskFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'goal_id',
        'status',
        'context_payload',
        'locked_resource_id',
        'conversation_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentTaskStatus::class,
            'context_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AgentGoal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(AgentGoal::class, 'goal_id');
    }

    /**
     * @return HasMany<PendingApproval, $this>
     */
    public function pendingApprovals(): HasMany
    {
        return $this->hasMany(PendingApproval::class, 'task_id');
    }

    /**
     * @return HasOne<ResourceLock, $this>
     */
    public function resourceLock(): HasOne
    {
        return $this->hasOne(ResourceLock::class, 'task_id');
    }

    /**
     * @return BelongsTo<AgentConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id');
    }
}
