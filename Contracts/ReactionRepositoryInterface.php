<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Contracts;

interface ReactionRepositoryInterface
{
    public function toggle(string $businessId, string $userId, string $postExternalId, string $type): array;
    public function listByPost(string $postExternalId): array;
}
