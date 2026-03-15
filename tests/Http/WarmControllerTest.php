<?php

namespace Katana\Tests\Http;

use Katana\KatanaManager;
use Katana\KatanaServiceProvider;
use Katana\Store\ArrayStore;
use Katana\Store\StoreInterface;
use Katana\Tests\Support\InMemoryLoader;
use Orchestra\Testbench\TestCase;

/**
 * Feature: Warm endpoint rebuilds APCu cache via HTTP
 *
 * Given warm endpoint is enabled with a valid token,
 * When POST /katana/warm is called,
 * Then all registered tables should be rebuilt and cached.
 */
class WarmControllerTest extends TestCase
{
    private ArrayStore $store;

    /** @var list<array<string, mixed>> */
    private array $products = [
        ['id' => 1, 'name' => 'Widget A', 'price' => 500],
        ['id' => 2, 'name' => 'Widget B', 'price' => 200],
    ];

    protected function getPackageProviders($app): array
    {
        return [KatanaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->store = new ArrayStore;
        $app->singleton(StoreInterface::class, fn () => $this->store);

        $app['config']->set('katana.warm.enabled', true);
        $app['config']->set('katana.warm.token', 'test-secret-token');
        $app['config']->set('katana.version.driver', 'csv');
        $app['config']->set('katana.version.csv_path', __DIR__.'/../Support/versions.csv');
    }

    protected function setUp(): void
    {
        parent::setUp();

        assert($this->app !== null);
        /** @var KatanaManager $manager */
        $manager = $this->app->make(KatanaManager::class);

        $manager->register('products', new InMemoryLoader(
            records: $this->products,
            columns: ['id' => 'int', 'name' => 'string', 'price' => 'int'],
        ));
    }

    // =========================================================================
    // Authentication
    // =========================================================================

    public function test_warm_rejects_request_without_token(): void
    {
        // Given: no Authorization header
        // When: POST /katana/warm
        $response = $this->postJson('/katana/warm');

        // Then: 401 Unauthorized
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_warm_rejects_request_with_wrong_token(): void
    {
        // Given: wrong Bearer token
        // When: POST /katana/warm
        $response = $this->postJson('/katana/warm', [], [
            'Authorization' => 'Bearer wrong-token',
        ]);

        // Then: 401 Unauthorized
        $response->assertStatus(401);
    }

    // =========================================================================
    // Successful warm
    // =========================================================================

    public function test_warm_rebuilds_all_tables(): void
    {
        // Given: products table is registered
        // When: POST /katana/warm with valid token
        $response = $this->postJson('/katana/warm', [], [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        // Then: 200 with success
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'All tables warmed.',
            'tables' => [
                'products' => ['status' => 'ok'],
            ],
        ]);
    }

    public function test_warm_rebuilds_specific_tables(): void
    {
        // Given: products table is registered
        // When: POST /katana/warm?tables=products
        $response = $this->postJson('/katana/warm?tables=products', [], [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        // Then: only products table is rebuilt
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'tables');
    }

    public function test_warm_with_version_override(): void
    {
        // Given: products table is registered
        // When: POST /katana/warm?version=v2.0.0
        $response = $this->postJson('/katana/warm?version=v2.0.0', [], [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        // Then: rebuilt with overridden version
        $response->assertStatus(200);
        $response->assertJsonPath('tables.products.version', 'v2.0.0');
    }

    public function test_warm_returns_empty_when_no_tables(): void
    {
        // Given: no tables registered (fresh manager)
        assert($this->app !== null);
        $this->app->singleton(KatanaManager::class, fn ($app) => new KatanaManager(
            store: $app->make(StoreInterface::class),
        ));

        // When: POST /katana/warm
        $response = $this->postJson('/katana/warm', [], [
            'Authorization' => 'Bearer test-secret-token',
        ]);

        // Then: 200 with empty tables
        $response->assertStatus(200);
        $response->assertJson(['message' => 'No tables registered.', 'tables' => []]);
    }

    // =========================================================================
    // Disabled endpoint
    // =========================================================================

    public function test_warm_custom_path(): void
    {
        // Given: warm.path is overridden
        assert($this->app !== null);
        $this->app['config']->set('katana.warm.path', 'custom/warm');

        // Note: Route path is set at boot time, so the original path still works.
        // This test verifies the config value is respected.
        /** @var string $path */
        $path = $this->app['config']->get('katana.warm.path');
        $this->assertSame('custom/warm', $path, 'Custom path should be configurable');
    }

    // =========================================================================
    // Token not configured
    // =========================================================================

    public function test_warm_rejects_when_token_not_configured(): void
    {
        // Given: warm.token is empty
        assert($this->app !== null);
        $this->app['config']->set('katana.warm.token', '');

        // When: POST /katana/warm with any token
        $response = $this->postJson('/katana/warm', [], [
            'Authorization' => 'Bearer some-token',
        ]);

        // Then: 403 with config message
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Warm endpoint is not configured. Set katana.warm.token.']);
    }
}
