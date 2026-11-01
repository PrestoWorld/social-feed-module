<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Storage\SocialFeed;

use PrestoWorld\Modules\SocialFeed\Contracts\ReactionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Storage\AbstractRepository;

final class ReactionRepository extends AbstractRepository implements ReactionRepositoryInterface
{
    public function toggle(string $businessId, string $userId, string $postExternalId, string $type): array
    {
        $existing = $this->db->select('*')->from($this->table('social_reactions'))
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('post_external_id', $postExternalId)
            ->run()->fetch();

        if ($existing === false) {
            $externalId = $this->uuid();
            $now = time();
            $this->db->insert($this->table('social_reactions'))->values([
                'id' => $this->uuid(),
                'external_id' => $externalId,
                'business_id' => $businessId,
                'user_id' => $userId,
                'post_external_id' => $postExternalId,
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ])->run();
            return ['action' => 'added', 'type' => $type];
        }

        $existing = (array) $existing;
        $id = $this->strId($existing);
        if ((string) $existing['type'] === $type) {
            $this->db->delete($this->table('social_reactions'))->where('id', $id)->run();
            return ['action' => 'removed'];
        }

        $this->db->update($this->table('social_reactions'), ['type' => $type, 'updated_at' => time()], ['id' => $id])->run();
        return ['action' => 'changed', 'type' => $type];
    }

    public function listByPost(string $postExternalId): array
    {
        $rows = $this->db->select('*')->from($this->table('social_reactions'))->where('post_external_id', $postExternalId)->run()->fetchAll();
        return $this->rows($rows);
    }
}
