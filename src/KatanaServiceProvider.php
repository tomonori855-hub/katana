<?php

namespace Katana;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Katana\Console\RebuildCommand;
use Katana\Contracts\VersionResolverInterface;
use Katana\Http\Controllers\WarmController;
use Katana\Http\Middleware\KatanaAuthMiddleware;
use Katana\Jobs\RebuildCacheJob;
use Katana\Loader\CsvVersionResolver;
use Katana\Store\ApcuStore;
use Katana\Store\StoreInterface;
use Katana\Version\CachedVersionResolver;
use Katana\Version\DatabaseVersionResolver;

class KatanaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/katana.php', 'katana');

        $this->app->singleton(StoreInterface::class, function ($app) {
            /** @var string $prefix */
            $prefix = $app['config']->get('katana.prefix', 'katana');

            return new ApcuStore($prefix);
        });

        $this->app->singleton(VersionResolverInterface::class, function ($app) {
            /** @var \Illuminate\Config\Repository $config */
            $config = $app->make('config');

            /** @var string $driver */
            $driver = $config->get('katana.version.driver', 'database');
            /** @var int $cacheTtl */
            $cacheTtl = $config->get('katana.version.cache_ttl', 300);

            $inner = match ($driver) {
                'database' => new DatabaseVersionResolver(
                    table: $config->get('katana.version.table', 'reference_versions'),
                    versionColumn: $config->get('katana.version.columns.version', 'version'),
                    startAtColumn: $config->get('katana.version.columns.activated_at', 'activated_at'),
                ),
                'csv' => new CsvVersionResolver(
                    versionsFilePath: $config->get('katana.version.csv_path', ''),
                ),
                default => throw new \InvalidArgumentException("Unknown version driver: {$driver}"),
            };

            if ($cacheTtl > 0) {
                return new CachedVersionResolver($inner, ttl: $cacheTtl);
            }

            return $inner;
        });

        $this->app->singleton(KatanaManager::class, function ($app) {
            /** @var array{ids?: int, record?: int, meta?: int, index?: int, ids_jitter?: int} $ttl */
            $ttl = $app['config']->get('katana.ttl', []);
            /** @var int|null $chunkSize */
            $chunkSize = $app['config']->get('katana.chunk_size');
            /** @var int $lockTtl */
            $lockTtl = $app['config']->get('katana.lock_ttl', 60);
            /** @var array<string, array{ttl?: array{ids?: int, record?: int, meta?: int, index?: int, ids_jitter?: int}, chunk_size?: int|null}> $tableConfigs */
            $tableConfigs = $app['config']->get('katana.tables', []);

            return new KatanaManager(
                store: $app->make(StoreInterface::class),
                defaultTtl: $ttl,
                defaultChunkSize: $chunkSize,
                lockTtl: $lockTtl,
                rebuildDispatcher: $this->buildRebuildDispatcher(),
                tableConfigs: $tableConfigs,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/katana.php' => config_path('katana.php'),
            ], 'katana-config');

            $this->commands([RebuildCommand::class]);
        }

        $this->registerWarmRoute();
    }

    private function registerWarmRoute(): void
    {
        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app->make('config');

        if (! $config->get('katana.warm.enabled', false)) {
            return;
        }

        /** @var string $path */
        $path = $config->get('katana.warm.path', 'katana/warm');

        Route::post($path, WarmController::class)
            ->middleware(KatanaAuthMiddleware::class)
            ->name('katana.warm');
    }

    /**
     * Build the rebuild dispatcher Closure based on config strategy.
     *
     * sync:     null (CacheProcessor falls back to synchronous rebuild)
     * queue:    dispatches RebuildCacheJob
     * callback: user registers their own via config (not built here)
     *
     * @return (\Closure(CacheRepository): void)|null
     */
    private function buildRebuildDispatcher(): ?\Closure
    {
        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app->make('config');

        /** @var string $strategy */
        $strategy = $config->get('katana.rebuild.strategy', 'sync');

        if ($strategy !== 'queue') {
            return null;
        }

        /** @var string|null $connection */
        $connection = $config->get('katana.rebuild.queue.connection');
        /** @var string|null $queue */
        $queue = $config->get('katana.rebuild.queue.queue');
        /** @var int $retry */
        $retry = $config->get('katana.rebuild.queue.retry', 3);

        return function (CacheRepository $repository) use ($connection, $queue, $retry): void {
            $job = new RebuildCacheJob($repository->table());
            $job->tries = $retry;

            if ($connection !== null) {
                $job->onConnection($connection);
            }
            if ($queue !== null) {
                $job->onQueue($queue);
            }

            dispatch($job);
        };
    }
}
