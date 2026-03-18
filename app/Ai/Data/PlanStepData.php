<?php

declare(strict_types=1);

namespace App\Ai\Data;

use App\Enums\AgentRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PlanStepData
{
    public function __construct(
        public int $order,
        public AgentRole $role,
        public string $action,
        public ?int $dependsOn,
        public string $onFailure,
    ) {}

    /**
     * Validate and create a PlanStepData from a raw array.
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $validator = Validator::make($data, [
            'order' => ['required', 'integer', 'min:1', 'max:8'],
            'role' => ['required', 'string', Rule::in(array_column(AgentRole::cases(), 'value'))],
            'action' => ['required', 'string', 'min:10'],
            'depends_on' => ['nullable', 'integer', 'min:1'],
            'on_failure' => ['required', 'string', Rule::in(['retry', 'escalate', 'halt'])],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'Invalid plan step: '.implode(', ', $validator->errors()->all()),
            );
        }

        $validated = $validator->validated();

        return new self(
            order: (int) $validated['order'],
            role: AgentRole::from($validated['role']),
            action: $validated['action'],
            dependsOn: isset($validated['depends_on']) ? (int) $validated['depends_on'] : null,
            onFailure: $validated['on_failure'],
        );
    }

    /**
     * Validate a full list of plan steps.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return list<self>
     *
     * @throws InvalidArgumentException
     */
    public static function validateSteps(array $steps): array
    {
        if (empty($steps)) {
            throw new InvalidArgumentException('Plan must contain at least one step.');
        }

        if (count($steps) > 8) {
            throw new InvalidArgumentException('Plan must not exceed 8 steps.');
        }

        $validated = [];
        $orders = [];

        foreach ($steps as $step) {
            $dto = self::fromArray($step);

            if (in_array($dto->order, $orders, true)) {
                throw new InvalidArgumentException("Duplicate step order: {$dto->order}");
            }

            $orders[] = $dto->order;

            if ($dto->dependsOn !== null && ! in_array($dto->dependsOn, $orders, true)) {
                throw new InvalidArgumentException(
                    "Step {$dto->order} depends on step {$dto->dependsOn} which has not been defined yet.",
                );
            }

            $validated[] = $dto;
        }

        usort($validated, fn (self $a, self $b) => $a->order <=> $b->order);

        return $validated;
    }
}
