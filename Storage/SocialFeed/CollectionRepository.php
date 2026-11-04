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
            $id = $this->uuid();
            $this->db->insert($this->table('social_collections'))->values(['id' => $id] + $values)->run();
            $values['id'] = $id;
        } else {
            $id = $this->strId($existing);
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
            // 002 đổi PK sang UUID không auto-increment -> insert bắt buộc có id,
            // nếu không sẽ fatal NOT NULL constraint failed.
            $this->db->insert($this->table('social_collection_items'))->values([
                'id' => $this->uuid(),
                'collection_id' => $collectionId,
                'post_external_id' => $postExternalId,
                'created_at' => time(),
            ])->run();
            // SQL thô: Cycle 2.23 không có Expression\Raw (fatal nếu dùng).
            $this->db->execute(
                'UPDATE ' . $this->table('social_collections') . ' SET item_count = item_count + 1 WHERE external_id = ?',
                [$collectionId],
            );
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
            // CASE WHEN thay cho GREATEST(): GREATEST cần SQLite >= 3.35,
            // CASE WHEN chạy được trên cả mysql / pgsql / sqlite mọi bản.
            $this->db->execute(
                'UPDATE ' . $this->table('social_collections')
                    . ' SET item_count = CASE WHEN item_count > 0 THEN item_count - 1 ELSE 0 END'
                    . ' WHERE external_id = ?',
                [$collectionId],
            );
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
