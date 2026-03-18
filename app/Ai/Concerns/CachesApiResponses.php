<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use Illuminate\Support\Facades\Cache;

trait CachesApiResponses
{
    /**
     * Wrap an API call with caching for read-only tools.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function cached(array $arguments, callable $callback): mixed
    {
        if (! $this->isReadOnly) {
            return $callback();
        }

        $ttl = $this->cacheTtl();

        if ($ttl === null) {
            return $callback();
        }

        $key = sprintf(
            'tool:%s:%s:%s',
            class_basename(static::class),
            $this->shop?->id ?? 'none',
            md5(json_encode($arguments, JSON_THROW_ON_ERROR))
        );

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Get the cache TTL in seconds based on category.
     */
    private function cacheTtl(): ?int
    {
        return null;
    }
}
