<?php

namespace Katana\Tests\Console;

use Illuminate\Testing\PendingCommand;
use Katana\KatanaManager;
use Katana\KatanaServiceProvider;
use Katana\Store\ArrayStore;
use Katana\Store\StoreInterface;
use Katana\Tests\Support\InMemoryLoader;
use Orchestra\Testbench\TestCase;

/**
 * Feature: katana:rebuild artisan command
 *
 * Given registered tables in the KatanaManager,
 * When running the katana:rebuild command,
 * Then cache should be populated for the specified tables.
 */
class RebuildCommandTest extends TestCase
{
    private ArrayStore $store;

    protected function getPackageProviders($app): array
    {
        return [KatanaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->store = new ArrayStore;
        $app->singleton(StoreInterface::class, fn () => $this->store);
    }

    private function manager(): KatanaManager
    {
        assert($this->app !== null);

        return $this->app->make(KatanaManager::class);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runArtisan(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        assert($result instanceof PendingCommand);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Rebuild all tables
    // -------------------------------------------------------------------------

    public function test_rebuild_all_registered_tables(): void
    {
        // Given: two tables registered
        $manager = $this->manager();
        $manager->register('users', new InMemoryLoader([
            ['id' => 1, 'name' => 'Alice'],
        ]));
        $manager->register('products', new InMemoryLoader([
            ['id' => 1, 'title' => 'Widget'],
        ]));

        // When: running katana:rebuild with no arguments
        $this->runArtisan('katana:rebuild')
            ->expectsOutputToContain('Rebuilding: users')
            ->expectsOutputToContain('Rebuilding: products')
            ->expectsOutputToContain('All rebuilds completed.')
            ->assertExitCode(0);

        // Then: both tables should be cached
        $this->assertIsArray(
            $this->store->getIds('users', 'v1'),
            'users cache should be populated after rebuild',
        );
        $this->assertIsArray(
            $this->store->getIds('products', 'v1'),
            'products cache should be populated after rebuild',
        );
    }

    // -------------------------------------------------------------------------
    // Rebuild specific tables
    // -------------------------------------------------------------------------

    public function test_rebuild_specific_table_only(): void
    {
        // Given: two tables registered
        $manager = $this->manager();
        $manager->register('users', new InMemoryLoader([
            ['id' => 1, 'name' => 'Alice'],
        ]));
        $manager->register('products', new InMemoryLoader([
            ['id' => 1, 'title' => 'Widget'],
        ]));

        // When: rebuilding only 'users'
        $this->runArtisan('katana:rebuild', ['table' => ['users']])
            ->expectsOutputToContain('Rebuilding: users')
            ->assertExitCode(0);

        // Then: only users should be cached
        $this->assertIsArray(
            $this->store->getIds('users', 'v1'),
            'users cache should be populated',
        );
        $this->assertFalse(
            $this->store->getIds('products', 'v1'),
            'products cache should NOT be populated when not specified',
        );
    }

    // -------------------------------------------------------------------------
    // --reference-version option
    // -------------------------------------------------------------------------

    public function test_rebuild_with_reference_version_option(): void
    {
        // Given: a table registered with default version 'v1'
        $manager = $this->manager();
        $manager->register('users', new InMemoryLoader(
            [['id' => 1, 'name' => 'Alice']],
            version: 'v1',
        ));

        // When: rebuilding with --reference-version=v2.0.0
        $this->runArtisan('katana:rebuild', ['--reference-version' => 'v2.0.0'])
            ->expectsOutputToContain('Using reference version: v2.0.0')
            ->expectsOutputToContain('version: v2.0.0')
            ->assertExitCode(0);

        // Then: cache should be stored under v2.0.0, not v1
        $this->assertIsArray(
            $this->store->getIds('users', 'v2.0.0'),
            'rebuild should use the overridden version for cache keys',
        );
        $this->assertFalse(
            $this->store->getIds('users', 'v1'),
            'rebuild should NOT store under the original Loader version',
        );
    }

    // -------------------------------------------------------------------------
    // No tables registered
    // -------------------------------------------------------------------------

    public function test_rebuild_with_no_tables_shows_warning(): void
    {
        // Given: no tables registered
        // When: running katana:rebuild
        $this->runArtisan('katana:rebuild')
            ->expectsOutputToContain('No tables registered.')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // Rebuild failure
    // -------------------------------------------------------------------------

    public function test_rebuild_failure_returns_failure_exit_code(): void
    {
        // Given: a loader that throws an exception
        $manager = $this->manager();

        $failingLoader = new class implements \Katana\Loader\LoaderInterface
        {
            public function load(): \Generator
            {
                throw new \RuntimeException('DB connection failed');
                yield; // @phpstan-ignore deadCode.unreachable
            }

            /** @return array<string, string> */
            public function columns(): array
            {
                return [];
            }

            /** @return list<array{columns: list<string>, unique: bool}> */
            public function indexes(): array
            {
                return [];
            }

            public function version(): string
            {
                return 'v1';
            }
        };

        $manager->register('failing_table', $failingLoader);

        // When: rebuilding the failing table
        $this->runArtisan('katana:rebuild', ['table' => ['failing_table']])
            ->expectsOutputToContain('Failed: DB connection failed')
            ->assertExitCode(1);
    }
}
