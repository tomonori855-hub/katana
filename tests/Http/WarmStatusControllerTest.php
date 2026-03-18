<?php

namespace Kura\Tests\Http;

use Kura\Http\Batch\BatchFinderInterface;
use Kura\Http\Batch\BatchSummary;
use Kura\KuraServiceProvider;
use Kura\Store\ArrayStore;
use Kura\Store\StoreInterface;
use Kura\Tests\Support\FakeBatchFinder;
use Orchestra\Testbench\TestCase;

/**
 * Feature: Warm status endpoint returns batch progress
 *
 * Given a batch was dispatched via POST /kura/warm (strategy=queue),
 * When GET /kura/warm/status/{batchId} is called,
 * Then the current batch progress should be returned.
 */
class WarmStatusControllerTest extends TestCase
{
    private FakeBatchFinder $batchFinder;

    protected function getPackageProviders($app): array
    {
        return [KuraServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->singleton(StoreInterface::class, fn () => new ArrayStore);
        $app['config']->set('kura.warm.enabled', true);
        $app['config']->set('kura.warm.token', 'test-secret-token');
        $app['config']->set('kura.version.driver', 'csv');
        $app['config']->set('kura.version.csv_path', __DIR__.'/../Support/versions.csv');

        $this->batchFinder = new FakeBatchFinder;
        $app->instance(BatchFinderInterface::class, $this->batchFinder);
    }

    // =========================================================================
    // Authentication
    // =========================================================================

    public function test_status_rejects_request_without_token(): void
    {
        // Given: no Authorization header
        // When: GET /kura/warm/status/some-batch-id
        $response = $this->getJson('/kura/warm/status/some-batch-id');

        // Then: 401 Unauthorized
        $response->assertStatus(401);
    }

    // =========================================================================
    // Batch not found
    // =========================================================================

    public function test_status_returns_404_for_unknown_batch_id(): void
    {
        // Given: no batch registered with this ID in the finder

        // When: GET /kura/warm/status/unknown-id
        $response = $this->getJson('/kura/warm/status/unknown-id', [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        // Then: 404 with message
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Batch not found.']);
    }

    // =========================================================================
    // Batch status
    // =========================================================================

    public function test_status_returns_batch_progress(): void
    {
        // Given: a batch with 3 total jobs, 1 pending, 0 failed, not finished
        $this->batchFinder->add(new BatchSummary(
            id: 'abc-123',
            totalJobs: 3,
            pendingJobs: 1,
            failedJobs: 0,
            finished: false,
            cancelled: false,
        ));

        // When: GET /kura/warm/status/abc-123
        $response = $this->getJson('/kura/warm/status/abc-123', [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        // Then: 200 with full progress
        $response->assertStatus(200);
        $response->assertJson([
            'batch_id' => 'abc-123',
            'total' => 3,
            'pending' => 1,
            'failed' => 0,
            'finished' => false,
            'cancelled' => false,
        ]);
    }

    public function test_status_returns_finished_true_when_batch_is_done(): void
    {
        // Given: a finished batch with no pending jobs
        $this->batchFinder->add(new BatchSummary(
            id: 'done-batch',
            totalJobs: 2,
            pendingJobs: 0,
            failedJobs: 0,
            finished: true,
            cancelled: false,
        ));

        // When: GET /kura/warm/status/done-batch
        $response = $this->getJson('/kura/warm/status/done-batch', [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        // Then: finished = true, pending = 0
        $response->assertStatus(200);
        $response->assertJsonPath('finished', true);
        $response->assertJsonPath('pending', 0);
    }
}
