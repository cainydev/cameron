<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Attributes\Category;
use App\Ai\Concerns\CachesApiResponses;
use App\Ai\Concerns\RateLimitsGoogleApi;
use App\Enums\AgentTaskStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ToolCategory;
use App\Events\ApprovalRequired;
use App\Models\AgentTask;
use App\Models\PendingApproval;
use App\Models\Shop;
use App\Services\GoogleApiService;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use ReflectionClass;
use Stringable;

/**
 * Base class for all HITL agent tools.
 *
 * Separates the LLM interface (handle) from the pure business logic (execute).
 * Tools that require human approval will log to PendingApprovals instead of
 * executing immediately when invoked by an agent.
 */
abstract class AbstractAgentTool implements Tool
{
    use CachesApiResponses, RateLimitsGoogleApi;

    /**
     * Whether this tool requires human approval before execution.
     */
    protected bool $requiresApproval = false;

    /**
     * Whether this tool only reads data (no side effects).
     */
    protected bool $isReadOnly = false;

    /**
     * Whether this tool should be hidden from the chat UI.
     * Hidden tools still execute normally — they just aren't surfaced to the user.
     */
    protected bool $hidden = false;

    /**
     * The active task ID for the current agent execution context.
     */
    protected ?int $activeTaskId = null;

    /**
     * The shop context for this tool invocation.
     */
    protected ?Shop $shop = null;

    /**
     * Whether this tool should be hidden from the chat UI tool call display.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * A human-readable label for this tool, optionally derived from its arguments.
     * Shown in the chat UI when the tool is invoked.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function label(array $arguments = []): string
    {
        // Default: convert CamelCase class name to "Title Case Words"
        return (string) preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename(static::class));
    }

    /**
     * Execute the tool's core business logic.
     *
     * @param  array<string, mixed>  $arguments
     */
    abstract public function execute(array $arguments): mixed;

    /**
     * Set the active task context for this tool invocation.
     */
    public function forTask(int $taskId): static
    {
        $this->activeTaskId = $taskId;

        return $this;
    }

    /**
     * Set the shop context for this tool invocation.
     */
    public function forShop(Shop $shop): static
    {
        $this->shop = $shop;

        return $this;
    }

    /**
     * Resolve the tool's category from the #[Category] attribute.
     */
    public function category(): ?ToolCategory
    {
        $ref = new ReflectionClass(static::class);
        $attrs = $ref->getAttributes(Category::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance()->category;
    }

    /**
     * Shop fields that must be non-null for this tool to be available.
     * Defaults to the category's requiredShopField(). Override for special cases.
     *
     * @return list<string>
     */
    public function requiredShopFields(): array
    {
        $field = $this->category()?->requiredShopField();

        return $field ? [$field] : [];
    }

    /**
     * Override the approval requirement at runtime.
     */
    public function setRequiresApproval(bool $requires): static
    {
        $this->requiresApproval = $requires;

        return $this;
    }

    /**
     * Build a GoogleApiService authenticated as the shop's owner,
     * falling back to the currently authenticated user when running in web context.
     */
    protected function googleApiService(): GoogleApiService
    {
        $user = $this->shop?->user ?? Auth::user();

        return new GoogleApiService($user);
    }

    /**
     * Handle the tool invocation from the AI SDK.
     *
     * If the tool requires approval, logs a PendingApproval record and returns
     * a message to the LLM. Otherwise, executes the business logic directly.
     */
    public function handle(Request $request): Stringable|string
    {
        if ($this->requiresApproval) {
            return $this->queueForApproval($request);
        }

        try {
            $this->checkRateLimit();

            $result = $this->cached($request->toArray(), fn () => $this->execute($request->toArray()));
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /**
     * Log this tool invocation to the pending_approvals table
     * and notify the LLM that it needs human sign-off.
     */
    protected function queueForApproval(Request $request): string
    {
        $taskId = $this->resolveActiveTaskId();

        $approval = PendingApproval::query()->create([
            'task_id' => $taskId,
            'tool_class' => static::class,
            'payload' => $request->toArray(),
            'reasoning' => $request['reason'] ?? 'No reason provided by agent.',
            'expires_at' => now()->addHours(24),
            'status' => ApprovalStatus::Waiting,
        ]);

        if ($taskId) {
            AgentTask::query()
                ->where('id', $taskId)
                ->update(['status' => AgentTaskStatus::WaitingApproval]);

            $task = AgentTask::query()->with('conversation')->find($taskId);
            $userId = $task?->conversation?->user_id;

            if ($userId) {
                ApprovalRequired::dispatch($userId, $taskId, $approval);
            }
        }

        return 'Action queued for human approval. Do not retry this action — a human operator will review it.';
    }

    /**
     * Resolve the active task ID from the explicit context or fallback.
     */
    protected function resolveActiveTaskId(): ?int
    {
        return $this->activeTaskId;
    }
}
