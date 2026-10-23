<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Headless\Handler;

use PrestoWorld\Modules\HeadlessCMS\Http\Query;
use PrestoWorld\Modules\SocialFeed\Contracts\ReactionRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ResourceHandlerInterface;

final class ReactionResourceHandler implements ResourceHandlerInterface
{
    public function __construct(
        private ReactionRepositoryInterface $repository,
        private string $businessId,
    ) {}

    public function list(Query $query): array
    {
        $postId = $query->filters['post_external_id'] ?? '';
        if ($postId === '') {
            return ['items' => [], 'total' => 0];
        }
        $result = $this->repository->listByPost($postId);

        return [
            'items' => $result,
            'total' => count($result),
        ];
    }

    public function read(string $id): ?array
    {
        return null;
    }

    public function create(array $data): array
    {
        return $this->repository->toggle(
            $this->businessId,
            $data['user_id'] ?? '',
            $data['post_external_id'] ?? '',
            $data['type'] ?? 'like',
        );
    }

    public function update(string $id, array $data): array
    {
        return $this->create($data);
    }

    public function delete(string $id): bool
    {
        return false;
    }
}
