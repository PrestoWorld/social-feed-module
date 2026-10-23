<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Headless;

use PrestoWorld\Modules\SocialFeed\Contracts\CollectionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\CommentRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\ReactionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\SocialPostRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Headless\Handler\CollectionResourceHandler;
use PrestoWorld\Modules\SocialFeed\Headless\Handler\CommentResourceHandler;
use PrestoWorld\Modules\SocialFeed\Headless\Handler\ReactionResourceHandler;
use PrestoWorld\Modules\SocialFeed\Headless\Handler\SocialPostResourceHandler;
use PrestoWorld\Modules\HeadlessCMS\Contracts\ProviderInterface;
use PrestoWorld\Modules\HeadlessCMS\Resource\ResourceDefinition;

final class SocialFeedProvider implements ProviderInterface
{
    public function __construct(
        private SocialPostRepositoryInterface $posts,
        private CommentRepositoryInterface $comments,
        private ReactionRepositoryInterface $reactions,
        private CollectionRepositoryInterface $collections,
        private string $businessId,
    ) {}

    public function namespace(): string
    {
        return 'social-feed';
    }

    public function resources(): array
    {
        return [
            'social_posts' => new ResourceDefinition(
                name: 'social_posts',
                handler: new SocialPostResourceHandler($this->posts, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update', 'delete'],
                publicRead: true,
            ),
            'social_comments' => new ResourceDefinition(
                name: 'social_comments',
                handler: new CommentResourceHandler($this->comments, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'delete'],
                publicRead: true,
            ),
            'social_reactions' => new ResourceDefinition(
                name: 'social_reactions',
                handler: new ReactionResourceHandler($this->reactions, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'delete'],
                publicRead: true,
            ),
            'social_collections' => new ResourceDefinition(
                name: 'social_collections',
                handler: new CollectionResourceHandler($this->collections, $this->businessId),
                key: 'external_id',
                operations: ['list', 'read', 'create', 'update', 'delete'],
                publicRead: true,
            ),
        ];
    }
}
