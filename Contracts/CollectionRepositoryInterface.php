<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Contracts;

interface CollectionRepositoryInterface
{
    public function list(string $businessId, int $offset, int $size): array;
    public function save(string $businessId, array $data): array;
    public function addItem(string $collectionId, string $postExternalId): bool;
    public function removeItem(string $collectionId, string $postExternalId): bool;
    public function isDefault(string $businessId): ?array;
    public function delete(string $businessId, string $externalId): bool;
}
