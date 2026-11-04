<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed;

use Cycle\Database\DatabaseInterface;
use PrestoWorld\Foundation\Admin\DashboardSubsystem;
use PrestoWorld\Modules\SocialFeed\Console\GenerateSlugsCommand;
use PrestoWorld\Modules\SocialFeed\Contracts\CollectionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\CommentRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\GroqClientInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\ReactionRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Contracts\SocialPostRepositoryInterface;
use PrestoWorld\Modules\SocialFeed\Headless\SocialFeedProvider;
use PrestoWorld\Modules\SocialFeed\Services\GroqClient;
use PrestoWorld\Modules\SocialFeed\Services\SlugComposer;
use PrestoWorld\Modules\SocialFeed\Services\SlugDispatcher;
use PrestoWorld\Modules\SocialFeed\Services\SlugProcessor;
use PrestoWorld\Modules\SocialFeed\Status\StatusRegistry;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\CollectionRepository;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\CommentRepository;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\ReactionRepository;
use PrestoWorld\Modules\SocialFeed\Storage\SocialFeed\SocialPostRepository;
use Witals\Framework\Application;
use Witals\Framework\Console\Kernel;
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
        $this->registerSlugServices();
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
        $this->registerConsoleCommands();

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
        $slug = fn (Application $app): ?SlugDispatcher => $this->resolve($app, SlugDispatcher::class);

        $bindings = [
            SocialPostRepositoryInterface::class => SocialPostRepository::class,
            CommentRepositoryInterface::class => CommentRepository::class,
            ReactionRepositoryInterface::class => ReactionRepository::class,
            CollectionRepositoryInterface::class => CollectionRepository::class,
        ];

        foreach ($bindings as $abstract => $concrete) {
            if ($concrete === SocialPostRepository::class) {
                $this->app->singleton($abstract, fn (Application $app) => new $concrete($db($app), $prefix($app), $slug($app)));
                continue;
            }

            $this->app->singleton($abstract, fn (Application $app) => new $concrete($db($app), $prefix($app)));
        }
    }

    /**
     * Dịch vụ sinh slug AI: composer (thuần, không I/O) + Groq client + processor.
     * SlugDispatcher được bind cùng chỗ để repository đẩy job ngay khi post tạo.
     */
    private function registerSlugServices(): void
    {
        $this->app->singleton(SlugComposer::class, fn (): SlugComposer => new SlugComposer());
        $this->app->singleton(GroqClient::class, fn (Application $app): GroqClient => new GroqClient($app));
        $this->app->singleton(GroqClientInterface::class, fn (Application $app): GroqClientInterface => $this->resolve($app, GroqClient::class));
        $this->app->singleton(SlugDispatcher::class, fn (Application $app): SlugDispatcher => new SlugDispatcher($app));
        $this->app->singleton(SlugProcessor::class, function (Application $app): SlugProcessor {
            $posts = $this->resolve($app, SocialPostRepositoryInterface::class);
            $groq = $this->resolve($app, GroqClientInterface::class);
            $composer = $this->resolve($app, SlugComposer::class);

            return new SlugProcessor($posts, $groq, $composer);
        });
    }

    private function registerConsoleCommands(): void
    {
        if (!$this->app->has(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        if (!$kernel instanceof Kernel) {
            return;
        }

        $kernel->register(GenerateSlugsCommand::class);
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
