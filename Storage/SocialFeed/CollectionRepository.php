<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Storage\SocialFeed;

use PrestoWorld\Modules\SocialFeed\Contracts\CollectionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Storage\AbstractRepository;

final class CollectionRepository extends AbstractRepository implements CollectionRepositoryInterface
{
    public function list(string $businessId, int $offset, int $size): array
    {
        $rows = $this->db->select('*')->from($this->table('social_collections'))
            ->where('business_id', $businessId)
            ->orderBy('is_default', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->offset($offset)
            ->limit($size)
            ->run()->fetchAll();
        return $this->rows($rows);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'collection_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'name' => (string) ($data['name'] ?? 'Yêu thích'),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'item_count' => (int) ($data['item_count'] ?? 0),
            'payload' => $this->encode($data['payload'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('social_collections'))->where('business_id', $businessId)->where('external_id', $externalId)->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('social_collections'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('social_collections'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);

        return $values;
    }

    public function addItem(string $collectionId, string $postExternalId): bool
    {
        $exists = $this->db->select('id')->from($this->table('social_collection_items'))
            ->where('collection_id', $collectionId)
            ->where('post_external_id', $postExternalId)
            ->run()->fetch();
        if ($exists === false) {
            $this->db->insert($this->table('social_collection_items'))->values([
                'collection_id' => $collectionId,
                'post_external_id' => $postExternalId,
                'created_at' => time(),
            ])->run();
            $this->db->update($this->table('social_collections'), ['item_count' => new \Cycle\Database\Expression\Raw('item_count + 1')], ['external_id' => $collectionId])->run();
        }
        return true;
    }

    public function removeItem(string $collectionId, string $postExternalId): bool
    {
        $result = $this->db->delete($this->table('social_collection_items'))
            ->where('collection_id', $collectionId)
            ->where('post_external_id', $postExternalId)
            ->run();
        if ($result > 0) {
            $this->db->update($this->table('social_collections'), ['item_count' => new \Cycle\Database\Expression\Raw('GREATEST(item_count - 1, 0)')], ['external_id' => $collectionId])->run();
        }
        return $result > 0;
    }

    public function isDefault(string $businessId): ?array
    {
        $row = $this->db->select('*')->from($this->table('social_collections'))->where('business_id', $businessId)->where('is_default', true)->run()->fetch();
        return $row === false ? null : $this->decoded($row);
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $this->db->delete($this->table('social_collection_items'))
            ->where('collection_id', $externalId)
            ->run();

        $result = $this->db->delete($this->table('social_collections'))
            ->where('business_id', $businessId)
            ->where('external_id', $externalId)
            ->run();

        return $result > 0;
    }
}
