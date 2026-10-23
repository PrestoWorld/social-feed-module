<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Headless\Handler;

use PrestoWorld\Modules\HeadlessCMS\Http\Query;
use PrestoWorld\Modules\SocialFeed\Contracts\CollectionRepositoryInterface;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ResourceHandlerInterface;

final class CollectionResourceHandler implements ResourceHandlerInterface
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private string $businessId,
    ) {}

    public function list(Query $query): array
    {
        $result = $this->repository->list($this->businessId, (int) $query->offset, (int) $query->limit);

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
