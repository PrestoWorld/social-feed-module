<?php

declare(strict_types=1);

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractTable;
use PrestoWorld\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $this->socialPosts($db, $prefix);
        $this->socialComments($db, $prefix);
        $this->socialReactions($db, $prefix);
        $this->socialCollections($db, $prefix);
        $this->socialCollectionItems($db, $prefix);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $tables = [
            'social_collection_items',
            'social_collections',
            'social_reactions',
            'social_comments',
            'social_posts',
        ];

        foreach ($tables as $name) {
            $full = $prefix . $name;
            if ($db->hasTable($full)) {
                $schema = $this->schema($db, $full);
                $schema->declareDropped();
                $schema->save();
            }
        }
    }

    private function socialPosts(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'social_posts');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('user_id')->string(64)->defaultValue('');
        $table->column('user_name')->string(255)->defaultValue('');
        $table->column('user_avatar')->string(500)->nullable();
        $table->column('type')->string(32)->defaultValue('status');
        $table->column('content')->text()->defaultValue('');
        $table->column('images')->text()->nullable();
        $table->column('gallery')->text()->nullable();
        $table->column('link_url')->string(500)->nullable();
        $table->column('link_title')->string(255)->nullable();
        $table->column('link_image')->string(500)->nullable();
        $table->column('media_url')->string(500)->nullable();
        $table->column('embed_url')->string(500)->nullable();
        $table->column('thumbnail_url')->string(500)->nullable();
        $table->column('platform')->string(32)->nullable();
        $table->column('duration')->bigInteger()->defaultValue(0);
        $table->column('status')->string(32)->defaultValue('published');
        $table->column('comment_count')->bigInteger()->defaultValue(0);
        $table->column('reaction_count')->bigInteger()->defaultValue(0);
        $table->column('share_count')->bigInteger()->defaultValue(0);
        $table->column('save_count')->bigInteger()->defaultValue(0);
        $table->column('view_count')->bigInteger()->defaultValue(0);
        $table->column('click_count')->bigInteger()->defaultValue(0);
        $table->column('play_count')->bigInteger()->defaultValue(0);
        $table->column('like_count')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'user_id']);
        $table->index(['business_id', 'type']);
        $table->index(['business_id', 'status']);
        $table->index(['created_at']);
        $table->save();
    }

    private function socialComments(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'social_comments');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('post_external_id')->string(64);
        $table->column('user_id')->string(64)->defaultValue('');
        $table->column('user_name')->string(255)->defaultValue('');
        $table->column('user_avatar')->string(500)->nullable();
        $table->column('content')->text()->defaultValue('');
        $table->column('parent_id')->bigInteger()->defaultValue(0);
        $table->column('status')->string(32)->defaultValue('published');
        $table->column('payload')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'post_external_id']);
        $table->index(['parent_id']);
        $table->save();
    }

    private function socialReactions(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'social_reactions');
        $table->primary('id');
        $table->column('external_id')->string(64);
        $table->column('business_id')->string(64);
        $table->column('user_id')->string(64)->defaultValue('');
        $table->column('post_external_id')->string(64);
        $table->column('type')->string(32)->defaultValue('like');
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'user_id', 'post_external_id'])->unique();
        $table->index(['post_external_id', 'type']);
        $table->save();
    }

    private function socialCollections(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'social_collections');
        $table->primary('id');
        $table->column('business_id')->string(64);
        $table->column('external_id')->string(64);
        $table->column('name')->string(255)->defaultValue('Yêu thích');
        $table->column('is_default')->boolean()->defaultValue(false);
        $table->column('item_count')->bigInteger()->defaultValue(0);
        $table->column('payload')->text()->nullable();
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->column('updated_at')->bigInteger()->defaultValue(0);
        $table->index(['business_id', 'external_id'])->unique();
        $table->index(['business_id', 'is_default']);
        $table->save();
    }

    private function socialCollectionItems(DatabaseInterface $db, string $prefix): void
    {
        $table = $this->table($db, $prefix, 'social_collection_items');
        $table->primary('id');
        $table->column('collection_id')->string(64);
        $table->column('post_external_id')->string(64);
        $table->column('created_at')->bigInteger()->defaultValue(0);
        $table->index(['collection_id', 'post_external_id'])->unique();
        $table->save();
    }

    private function table(DatabaseInterface $db, string $prefix, string $name): AbstractTable
    {
        return $this->schema($db, $prefix . $name);
    }

    private function schema(DatabaseInterface $db, string $name): AbstractTable
    {
        $table = $db->table($name);
        if (!$table instanceof \Cycle\Database\Table) {
            throw new \RuntimeException("Expected concrete Cycle table for {$name}, got " . $table::class);
        }
        return $table->getSchema();
    }
};
