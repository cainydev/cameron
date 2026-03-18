<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AgentConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use function Laravel\Ai\agent;

/**
 * Generates a short title for a Cameron chat conversation
 * based on the first user message.
 */
class GenerateConversationTitle implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $conversationId,
        public string $firstMessage,
    ) {}

    public function handle(): void
    {
        $conversation = AgentConversation::query()->find($this->conversationId);

        if (! $conversation || $conversation->title !== null) {
            return;
        }

        $response = agent(
            instructions: 'You generate short, descriptive titles for chat conversations. Given the first message, return a concise title (3-6 words) that summarises the topic. Do not use quotes or punctuation at the end.',
        )->prompt($this->firstMessage);

        $title = trim((string) $response);

        if ($title) {
            $conversation->update(['title' => $title]);
        }
    }
}
