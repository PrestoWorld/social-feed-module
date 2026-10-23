<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Headless\Handler;

use PrestoWorld\Modules\HeadlessCMS\Http\Query;
use PrestoWorld\Modules\SocialFeed\Contracts\CommentRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ResourceHandlerInterface;

final class CommentResourceHandler implements ResourceHandlerInterface
{
    public function __construct(
        private CommentRepositoryInterface $repository,
        private string $businessId,
    ) {}

    public function list(Query $query): array
    {
        $postId = $query->filters['post_external_id'] ?? '';
        if ($postId === '') {
            return ['items' => [], 'total' => 0];
        }
        $result = $this->repository->listByPost($postId, (int) $query->offset, (int) $query->limit);

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
        return $this->repository->save($this->businessId, $data);
    }

    public function update(string $id, array $data): array
    {
        return $this->repository->save($this->businessId, array_merge($data, ['external_id' => $id]));
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($this->businessId, $id);
    }
}
