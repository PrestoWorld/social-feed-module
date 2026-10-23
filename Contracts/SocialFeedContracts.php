<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Contracts;

interface SocialPostRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array;
    public function find(string $businessId, string $externalId): ?array;
    public function save(string $businessId, array $data): array;
    public function delete(string $businessId, string $externalId): bool;
    public function toggleStatus(string $businessId, string $externalId, string $status): bool;
}

interface CommentRepositoryInterface
{
    public function listByPost(string $postExternalId, int $offset, int $size): array;
    public function save(string $businessId, array $data): array;
    public function delete(string $businessId, string $externalId): bool;
}

interface ReactionRepositoryInterface
{
    public function toggle(string $businessId, string $userId, string $postExternalId, string $type): array;
    public function listByPost(string $postExternalId): array;
}

interface CollectionRepositoryInterface
{
    public function list(string $businessId, int $offset, int $size): array;
    public function save(string $businessId, array $data): array;
    public function addItem(string $collectionId, string $postExternalId): bool;
    public function removeItem(string $collectionId, string $postExternalId): bool;
    public function isDefault(string $businessId): ?array;
    public function delete(string $businessId, string $externalId): bool;
}
