<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Contracts;

interface CommentRepositoryInterface
{
    public function listByPost(string $postExternalId, int $offset, int $size): array;
    public function save(string $businessId, array $data): array;
    public function delete(string $businessId, string $externalId): bool;
}
