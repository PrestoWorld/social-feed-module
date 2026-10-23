<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Storage\SocialFeed;

use PrestoWorld\Modules\SocialFeed\Contracts\CommentRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Storage\AbstractRepository;

final class CommentRepository extends AbstractRepository implements CommentRepositoryInterface
{
    public function listByPost(string $postExternalId, int $offset, int $size): array
    {
        $rows = $this->db->select('*')->from($this->table('social_comments'))
            ->where('post_external_id', $postExternalId)
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
            $externalId = 'comment_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'post_external_id' => $data['post_external_id'] ?? '',
            'user_id' => $data['user_id'] ?? '',
            'user_name' => $data['user_name'] ?? '',
            'user_avatar' => $data['user_avatar'] ?? null,
            'content' => (string) ($data['content'] ?? ''),
            'parent_id' => (int) ($data['parent_id'] ?? 0),
            'status' => (string) ($data['status'] ?? 'published'),
            'payload' => $this->encode($data['payload'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('social_comments'))->where('business_id', $businessId)->where('external_id', $externalId)->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('social_comments'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('social_comments'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);

        return $values;
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $result = $this->db->delete($this->table('social_comments'))->where('business_id', $businessId)->where('external_id', $externalId)->run();
        return $result > 0;
    }
}
