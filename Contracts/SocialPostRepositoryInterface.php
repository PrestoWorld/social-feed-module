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

    /**
     * Lấy post đang chờ sinh slug AI: slug_generated = false, sắp xếp retries ASC
     * để post thất bại nhiều lần bị đẩy ra sau.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingSlug(int $limit): array;

    /** Đánh dấu slug đã sinh thành công: slug_generated = true. */
    public function markSlugGenerated(string $id, string $slug): bool;

    /** Sinh slug thất bại: giữ slug hiện tại, slug_generated = false, retries + 1. */
    public function markSlugFailed(string $id): bool;
}
