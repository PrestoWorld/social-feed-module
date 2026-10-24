<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Status;

/**
 * Registry of social post statuses extended by other modules.
 *
 * Modules (e.g. BookRental) register additional status values during boot so
 * the SocialFeed UI can recognize and render them (e.g. 'rented' for book
 * rental posts).
 */
final class StatusRegistry
{
    /** @var array<string, array{label: string, description?: string, color?: string}> */
    private array $statuses = [];

    public function register(string $status, array $meta): void
    {
        $this->statuses[$status] = array_merge(['label' => $status], $meta);
    }

    public function has(string $status): bool
    {
        return isset($this->statuses[$status]);
    }

    /** @return array<string, array{label: string, description?: string, color?: string}> */
    public function all(): array
    {
        return $this->statuses;
    }

    public function meta(string $status): ?array
    {
        return $this->statuses[$status] ?? null;
    }
}
