<?php

namespace App\Http\Controllers\Kura;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Kura\Jobs\RebuildCacheJob;
use Kura\KuraManager;

class WarmController
{
    /**
     * Rebuild cache for specified tables (or all registered tables).
     *
     * POST /kura/warm
     * POST /kura/warm?tables=products,categories
     * POST /kura/warm?version=v2.0.0
     *
     * Customize this controller freely — authentication, logging,
     * additional validation, Slack notifications, etc.
     *
     * Register your customized controller in config/kura.php:
     *   'warm' => ['controller' => \App\Http\Controllers\Kura\WarmController::class]
     */
    public function __invoke(Request $request, KuraManager $manager): JsonResponse
    {
        /** @var string|null $version */
        $version = $request->query('version');
        $version = ($version !== null && $version !== '') ? $version : null;

        if ($version !== null) {
            $manager->setVersionOverride($version);
        }

        /** @var string|null $tablesParam */
        $tablesParam = $request->query('tables');
        $tables = ($tablesParam !== null && $tablesParam !== '')
            ? explode(',', $tablesParam)
            : $manager->registeredTables();

        if ($tables === []) {
            return new JsonResponse(['message' => 'No tables registered.', 'tables' => []], 200);
        }

        /** @var string $strategy */
        $strategy = config('kura.rebuild.strategy', 'sync');

        return match ($strategy) {
            'queue' => $this->dispatchBatch($manager, $tables, $version),
            default => $this->rebuildSync($manager, $tables),
        };
    }

    /**
     * Dispatch one RebuildCacheJob per table as a Bus batch.
     * Returns immediately — workers process tables in parallel.
     *
     * @param  list<string>  $tables
     */
    private function dispatchBatch(KuraManager $manager, array $tables, ?string $version): JsonResponse
    {
        if (! Schema::hasTable('job_batches')) {
            return new JsonResponse([
                'message' => 'job_batches table is missing. Run: php artisan queue:batches-table && php artisan migrate',
            ], 500);
        }

        /** @var string|null $connection */
        $connection = config('kura.rebuild.queue.connection');
        /** @var string|null $queue */
        $queue = config('kura.rebuild.queue.queue');

        $jobs = array_map(
            fn (string $table) => (new RebuildCacheJob($table, $version))
                ->onConnection($connection)
                ->onQueue($queue),
            $tables,
        );

        $batch = Bus::batch($jobs)->dispatch();

        $tableVersions = [];
        foreach ($tables as $table) {
            $tableVersions[$table] = ['status' => 'dispatched', 'version' => $manager->repository($table)->version()];
        }

        return new JsonResponse([
            'message' => 'Rebuild dispatched.',
            'batch_id' => $batch->id,
            'tables' => $tableVersions,
        ], 202);
    }

    /**
     * Rebuild all tables synchronously in the current request.
     *
     * @param  list<string>  $tables
     */
    private function rebuildSync(KuraManager $manager, array $tables): JsonResponse
    {
        $results = [];

        foreach ($tables as $table) {
            try {
                $repo = $manager->repository($table);
                $manager->rebuild($table);
                $results[$table] = ['status' => 'ok', 'version' => $repo->version()];
            } catch (\Throwable $e) {
                $results[$table] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        $hasError = in_array('error', array_column($results, 'status'), true);

        return new JsonResponse([
            'message' => $hasError ? 'Some tables failed.' : 'All tables warmed.',
            'tables' => $results,
        ], $hasError ? 500 : 200);
    }
}
