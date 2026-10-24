<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Storage\SocialFeed;

use Cycle\Database\Query\SelectQuery;
use PrestoWorld\Modules\SocialFeed\Contracts\SocialPostRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Storage\AbstractRepository;

final class SocialPostRepository extends AbstractRepository implements SocialPostRepositoryInterface
{
    public function search(string $businessId, array $filters, int $offset, int $size, array $sort = []): array
    {
        [$column, $direction] = $this->ordering($sort);
        $query = $this->applyFilters($this->db->select('*')->from($this->table('social_posts')), $businessId, $filters);
        $items = $query->orderBy($column, $direction)->offset($offset)->limit($size)->run()->fetchAll();
        $total = $this->applyFilters($this->db->select('*')->from($this->table('social_posts')), $businessId, $filters)->count();
        return ['items' => $this->rows($items), 'total' => $total];
    }

    public function find(string $businessId, string $externalId): ?array
    {
        $row = $this->db->select('*')->from($this->table('social_posts'))->where('business_id', $businessId)->where('external_id', $externalId)->run()->fetch();
        return $row === false ? null : $this->decoded($row);
    }

    public function save(string $businessId, array $data): array
    {
        $externalId = (string) ($data['external_id'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            $externalId = 'post_' . bin2hex(random_bytes(8));
        }
        $now = time();

        $status = (string) ($data['status'] ?? 'published');
        $allowed = ['published', 'draft', 'archived', 'rented'];
        if (!in_array($status, $allowed, true)) {
            $status = 'published';
        }

        $values = [
            'business_id' => $businessId,
            'external_id' => $externalId,
            'user_id' => $data['user_id'] ?? '',
            'user_name' => $data['user_name'] ?? '',
            'user_avatar' => $data['user_avatar'] ?? null,
            'type' => (string) ($data['type'] ?? 'status'),
            'content' => (string) ($data['content'] ?? ''),
            'images' => isset($data['images']) && is_array($data['images']) ? json_encode($data['images']) : ($data['images'] ?? null),
            'gallery' => isset($data['gallery']) && is_array($data['gallery']) ? json_encode($data['gallery']) : ($data['gallery'] ?? null),
            'link_url' => $data['link_url'] ?? null,
            'link_title' => $data['link_title'] ?? null,
            'link_image' => $data['link_image'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'embed_url' => $data['embed_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'platform' => $data['platform'] ?? null,
            'duration' => (int) ($data['duration'] ?? 0),
            'status' => $status,
            'comment_count' => (int) ($data['comment_count'] ?? 0),
            'reaction_count' => (int) ($data['reaction_count'] ?? 0),
            'share_count' => (int) ($data['share_count'] ?? 0),
            'save_count' => (int) ($data['save_count'] ?? 0),
            'view_count' => (int) ($data['view_count'] ?? 0),
            'click_count' => (int) ($data['click_count'] ?? 0),
            'play_count' => (int) ($data['play_count'] ?? 0),
            'like_count' => (int) ($data['like_count'] ?? 0),
            'payload' => $this->encode($data['payload'] ?? []),
            'created_at' => (int) ($data['created_at'] ?? $now),
            'updated_at' => (int) ($data['updated_at'] ?? $now),
        ];

        $existing = $this->db->select('id')->from($this->table('social_posts'))->where('business_id', $businessId)->where('external_id', $externalId)->run()->fetch();

        if ($existing === false) {
            $result = $this->db->insert($this->table('social_posts'))->values($values)->run();
            $values['id'] = (int) $result;
        } else {
            $id = $this->int($existing, 'id');
            $this->db->update($this->table('social_posts'), $values, ['id' => $id])->run();
            $values['id'] = $id;
        }

        $values['payload'] = $this->decode($values['payload']);
        $values['images'] = $this->decodeJson($values['images']);
        $values['gallery'] = $this->decodeJson($values['gallery']);

        return $values;
    }

    public function delete(string $businessId, string $externalId): bool
    {
        $result = $this->db->delete($this->table('social_posts'))->where('business_id', $businessId)->where('external_id', $externalId)->run();
        return $result > 0;
    }

    public function toggleStatus(string $businessId, string $externalId, string $status): bool
    {
        $allowed = ['published', 'draft', 'archived', 'rented'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $this->db->update($this->table('social_posts'), ['status' => $status, 'updated_at' => time()], ['business_id' => $businessId, 'external_id' => $externalId])->run();
        return true;
    }

    public function incrementView(string $externalId): void
    {
        $this->db->update($this->table('social_posts'), ['view_count' => new \Cycle\Database\Expression\Raw('view_count + 1'), 'updated_at' => time()], ['external_id' => $externalId])->run();
    }

    public function incrementClick(string $externalId): void
    {
        $this->db->update($this->table('social_posts'), ['click_count' => new \Cycle\Database\Expression\Raw('click_count + 1'), 'updated_at' => time()], ['external_id' => $externalId])->run();
    }

    public function incrementPlay(string $externalId): void
    {
        $this->db->update($this->table('social_posts'), ['play_count' => new \Cycle\Database\Expression\Raw('play_count + 1'), 'updated_at' => time()], ['external_id' => $externalId])->run();
    }

    private function applyFilters(SelectQuery $query, string $businessId, array $filters): SelectQuery
    {
        $query->where('business_id', $businessId);
        if (!empty($filters['keyword'])) {
            $query->where(fn ($q) => $q->where('content', 'LIKE', '%'.$filters['keyword'].'%')->orWhere('user_name', 'LIKE', '%'.$filters['keyword'].'%'));
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        return $query;
    }

    private function ordering(array $sort): array
    {
        $column = 'created_at'; $direction = 'DESC';
        foreach ($sort as $key => $value) {
            $key = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            if (!in_array($key, ['created_at', 'id', 'updated_at', 'reaction_count', 'comment_count', 'view_count', 'play_count', 'like_count'], true)) continue;
            $column = $key; $direction = strtolower((string)$value) === 'asc' ? 'ASC' : 'DESC'; break;
        }
        return [$column, $direction];
    }
}
