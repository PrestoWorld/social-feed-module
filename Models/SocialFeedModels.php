<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Models;

class SocialPost
{
    public int $id;
    public string $businessId;
    public string $externalId;
    public string $userId;
    public string $userName;
    public ?string $userAvatar;
    public string $type;
    public string $content;
    public array $images;
    public array $gallery;
    public ?string $linkUrl;
    public ?string $linkTitle;
    public ?string $linkImage;
    public string $status;
    public int $commentCount;
    public int $reactionCount;
    public int $shareCount;
    public int $saveCount;
    public int $createdAt;
    public int $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->businessId = (string) ($data['business_id'] ?? '');
        $this->externalId = (string) ($data['external_id'] ?? '');
        $this->userId = (string) ($data['user_id'] ?? '');
        $this->userName = (string) ($data['user_name'] ?? '');
        $this->userAvatar = $data['user_avatar'] ?? null;
        $this->type = (string) ($data['type'] ?? 'status');
        $this->content = (string) ($data['content'] ?? '');
        $this->images = is_array($data['images'] ?? null) ? $data['images'] : (is_string($data['images'] ?? null) ? json_decode((string) $data['images'], true) ?? [] : []);
        $this->gallery = is_array($data['gallery'] ?? null) ? $data['gallery'] : (is_string($data['gallery'] ?? null) ? json_decode((string) $data['gallery'], true) ?? [] : []);
        $this->linkUrl = $data['link_url'] ?? null;
        $this->linkTitle = $data['link_title'] ?? null;
        $this->linkImage = $data['link_image'] ?? null;
        $this->status = (string) ($data['status'] ?? 'published');
        $this->commentCount = (int) ($data['comment_count'] ?? 0);
        $this->reactionCount = (int) ($data['reaction_count'] ?? 0);
        $this->shareCount = (int) ($data['share_count'] ?? 0);
        $this->saveCount = (int) ($data['save_count'] ?? 0);
        $this->createdAt = (int) ($data['created_at'] ?? 0);
        $this->updatedAt = (int) ($data['updated_at'] ?? 0);
    }
}

class Video
{
    public int $id;
    public string $businessId;
    public string $externalId;
    public string $userId;
    public string $userName;
    public string $title;
    public string $description;
    public string $videoUrl;
    public ?string $embedUrl;
    public ?string $thumbnailUrl;
    public string $platform;
    public int $duration;
    public string $status;
    public int $viewCount;
    public int $likeCount;
    public int $createdAt;
    public int $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->businessId = (string) ($data['business_id'] ?? '');
        $this->externalId = (string) ($data['external_id'] ?? '');
        $this->userId = (string) ($data['user_id'] ?? '');
        $this->userName = (string) ($data['user_name'] ?? '');
        $this->title = (string) ($data['title'] ?? '');
        $this->description = (string) ($data['description'] ?? '');
        $this->videoUrl = (string) ($data['video_url'] ?? '');
        $this->embedUrl = $data['embed_url'] ?? null;
        $this->thumbnailUrl = $data['thumbnail_url'] ?? null;
        $this->platform = (string) ($data['platform'] ?? 'youtube');
        $this->duration = (int) ($data['duration'] ?? 0);
        $this->status = (string) ($data['status'] ?? 'published');
        $this->viewCount = (int) ($data['view_count'] ?? 0);
        $this->likeCount = (int) ($data['like_count'] ?? 0);
        $this->createdAt = (int) ($data['created_at'] ?? 0);
        $this->updatedAt = (int) ($data['updated_at'] ?? 0);
    }
}

class Podcast
{
    public int $id;
    public string $businessId;
    public string $externalId;
    public string $userId;
    public string $userName;
    public string $title;
    public string $description;
    public string $audioUrl;
    public ?string $embedUrl;
    public ?string $coverImage;
    public string $platform;
    public int $episodeNumber;
    public int $season;
    public int $duration;
    public string $status;
    public int $playCount;
    public int $likeCount;
    public int $createdAt;
    public int $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->businessId = (string) ($data['business_id'] ?? '');
        $this->externalId = (string) ($data['external_id'] ?? '');
        $this->userId = (string) ($data['user_id'] ?? '');
        $this->userName = (string) ($data['user_name'] ?? '');
        $this->title = (string) ($data['title'] ?? '');
        $this->description = (string) ($data['description'] ?? '');
        $this->audioUrl = (string) ($data['audio_url'] ?? '');
        $this->embedUrl = $data['embed_url'] ?? null;
        $this->coverImage = $data['cover_image'] ?? null;
        $this->platform = (string) ($data['platform'] ?? 'spotify');
        $this->episodeNumber = (int) ($data['episode_number'] ?? 0);
        $this->season = (int) ($data['season'] ?? 1);
        $this->duration = (int) ($data['duration'] ?? 0);
        $this->status = (string) ($data['status'] ?? 'published');
        $this->playCount = (int) ($data['play_count'] ?? 0);
        $this->likeCount = (int) ($data['like_count'] ?? 0);
        $this->createdAt = (int) ($data['created_at'] ?? 0);
        $this->updatedAt = (int) ($data['updated_at'] ?? 0);
    }
}
