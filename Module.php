<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Foundation\Admin\DashboardSubsystem;
use PrestoWorld\Modules\SocialFeed\Contracts\CollectionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\CommentRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\ReactionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\SocialPostRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Headless\SocialFeedProvider;
use PrestoWorld\Modules\SocialFeed\Status\StatusRegistry;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\CollectionRepository;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\CommentRepository;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\ReactionRepository;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\SocialPostRepository;
use Witals\Framework\Application;
use Witals\Framework\Module\Module as WitalsModule;

class Module extends WitalsModule
{
    public function __construct(
        protected Application $app,
        protected string $path = '',
        protected array $metadata = [],
    ) {
        if ($path === '') {
            $path = __DIR__;
        }
        if ($metadata === []) {
            $metadata = ['name' => 'social-feed'];
        }
        parent::__construct($app, $path, $metadata);
    }

    public function register(): void
    {
        $this->bindRepositories();
        $this->app->singleton(StatusRegistry::class, fn (): StatusRegistry => new StatusRegistry());

        $this->app->singleton(SocialFeedProvider::class, function (Application $app): SocialFeedProvider {
            return new SocialFeedProvider(
                $this->resolve($app, SocialPostRepositoryInterface::class),
                $this->resolve($app, CommentRepositoryInterface::class),
                $this->resolve($app, ReactionRepositoryInterface::class),
                $this->resolve($app, CollectionRepositoryInterface::class),
                $this->headlessBusinessId($app),
            );
        });
    }

    public function boot(): void
    {
        $this->registerDefaultStatuses();

        DashboardSubsystem::register(
            $this->app,
            'social-feed',
            'Social Feed',
            'Bài viết, quảng cáo, ảnh gallery, video, podcast, sách',
            'Feed',
            10,
            'content',
        );
    }

    private function registerDefaultStatuses(): void
    {
        $registry = $this->app->make(StatusRegistry::class);
        foreach (['published' => 'Đã xuất bản', 'draft' => 'Bản nháp', 'archived' => 'Lưu trữ'] as $status => $label) {
            if (!$registry->has($status)) {
                $registry->register($status, ['label' => $label]);
            }
        }
    }

    public function getName(): string { return 'SocialFeed'; }

    private function bindRepositories(): void
    {
        $prefix = fn (Application $app): string => (string) ($app->config('social-feed.table_prefix', 'pw_') ?: 'pw_');
        $db = fn (Application $app): DatabaseInterface => $this->resolveDatabase($app);

        $bindings = [
            SocialPostRepositoryInterface::class => SocialPostRepository::class,
            CommentRepositoryInterface::class => CommentRepository::class,
            ReactionRepositoryInterface::class => ReactionRepository::class,
            CollectionRepositoryInterface::class => CollectionRepository::class,
        ];

        foreach ($bindings as $abstract => $concrete) {
            $this->app->singleton($abstract, fn (Application $app) => new $concrete($db($app), $prefix($app)));
        }
    }

    private function headlessBusinessId(Application $app): string
    {
        return (string) ($app->config('social-feed.headless.business_id', ''));
    }

    private function resolve(Application $app, string $abstract): object
    {
        $instance = $app->make($abstract);
        if (!$instance instanceof $abstract) {
            throw new \RuntimeException(sprintf('%s is not bound in the container.', $abstract));
        }
        return $instance;
    }

    private function resolveDatabase(Application $app): DatabaseInterface
    {
        $db = $app->make(DatabaseInterface::class);
        if (!$db instanceof DatabaseInterface) {
            throw new \RuntimeException('DatabaseInterface is not bound in the container.');
        }
        return $db;
    }
}
