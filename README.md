# Katana

A Laravel package that provides a **QueryBuilder-compatible interface over APCu cache** for reference data.

Load data once from DB or CSV, cache it in APCu, and query it with Laravel's familiar fluent API — no database queries at runtime.

## Features

- **Laravel QueryBuilder API** — `where`, `orderBy`, `paginate`, `find`, `count`, `sum`, etc.
- **APCu-backed** — Sub-millisecond reads from shared memory
- **Generator-based** — Low memory usage for large datasets
- **Index acceleration** — Binary search indexes and composite index hashmaps for O(1) lookups
- **Self-Healing** — Automatic cache rebuild on eviction
- **Version management** — Seamless version switching via DB or CSV

## Requirements

- PHP ^8.4
- Laravel ^12.0
- APCu extension

## Installation

```bash
composer require tomonori/katana
```

Publish the config:

```bash
php artisan vendor:publish --tag=katana-config
```

## Quick Start

### 1. Implement a Loader

```php
use Katana\Loader\LoaderInterface;

class ProductLoader implements LoaderInterface
{
    public function load(): \Generator
    {
        foreach (Product::cursor() as $product) {
            yield $product->toArray();
        }
    }

    public function columns(): array
    {
        return ['id' => 'int', 'name' => 'string', 'country' => 'string', 'price' => 'float'];
    }

    public function indexes(): array
    {
        return [
            ['columns' => ['country'], 'unique' => false],
            ['columns' => ['country', 'category'], 'unique' => false],
        ];
    }

    public function version(): string
    {
        return 'v1.0.0';
    }
}
```

### 2. Register the Table

```php
// In a ServiceProvider
use Katana\Facades\Katana;

Katana::register('products', new ProductLoader, primaryKey: 'id');
```

### 3. Build the Cache

```bash
php artisan katana:rebuild
php artisan katana:rebuild products              # specific table
php artisan katana:rebuild --reference-version=v2.0.0  # explicit version
```

### 4. Query

```php
use Katana\Facades\Katana;

// Basic queries
$products = Katana::table('products')->where('country', 'JP')->get();
$product  = Katana::table('products')->find(42);
$count    = Katana::table('products')->where('active', true)->count();

// Sorting & pagination
$page = Katana::table('products')
    ->orderBy('price', 'desc')
    ->paginate(20);

// Aggregates
$max = Katana::table('products')->max('price');
$avg = Katana::table('products')->where('country', 'JP')->avg('price');

// ROW constructor IN (Katana extension)
$results = Katana::table('cart')
    ->whereRowValuesIn(['user_id', 'item_id'], [[1, 10], [2, 20]])
    ->get();
```

## Supported Query Methods

### WHERE

`where`, `orWhere`, `whereNot`, `orWhereNot`, `whereIn`, `whereNotIn`, `orWhereIn`, `orWhereNotIn`, `whereNull`, `whereNotNull`, `orWhereNull`, `orWhereNotNull`, `whereBetween`, `whereNotBetween`, `orWhereBetween`, `orWhereNotBetween`, `whereBetweenColumns`, `whereNotBetweenColumns`, `orWhereBetweenColumns`, `orWhereNotBetweenColumns`, `whereValueBetween`, `whereValueNotBetween`, `orWhereValueBetween`, `orWhereValueNotBetween`, `whereLike`, `whereNotLike`, `orWhereLike`, `orWhereNotLike`, `whereColumn`, `orWhereColumn`, `whereAll`, `orWhereAll`, `whereAny`, `orWhereAny`, `whereNone`, `orWhereNone`, `whereNullSafeEquals`, `orWhereNullSafeEquals`, `whereExists`, `orWhereExists`, `whereNotExists`, `orWhereNotExists`, `whereNested`, `whereFilter`, `orWhereFilter`, `whereRowValuesIn`, `whereRowValuesNotIn`, `orWhereRowValuesIn`, `orWhereRowValuesNotIn`

### ORDER BY

`orderBy`, `orderByDesc`, `latest`, `oldest`, `inRandomOrder`, `reorder`, `reorderDesc`

### LIMIT / OFFSET

`limit`, `offset`, `take`, `skip`, `forPage`, `forPageBeforeId`, `forPageAfterId`

### Retrieval

`get`, `first`, `sole`, `soleValue`, `find`, `findOr`, `value`, `cursor`, `pluck`, `implode`

### Aggregates

`count`, `min`, `max`, `sum`, `avg`, `average`, `exists`, `doesntExist`, `existsOr`, `doesntExistOr`

### Pagination

`paginate`, `simplePaginate`

## Configuration

```php
// config/katana.php
return [
    'prefix' => 'katana',

    'ttl' => [
        'ids'        => 3600,   // 1 hour (shortest — rebuild trigger)
        'meta'       => 4800,
        'record'     => 4800,
        'index'      => 4800,
        'ids_jitter' => 600,    // random 0–600s to prevent thundering herd
    ],

    'chunk_size' => null,       // null = no chunking
    'lock_ttl'   => 60,

    'rebuild' => [
        'strategy' => 'sync',   // 'sync', 'queue', or 'callback'
        'queue' => [
            'connection' => null,
            'queue'      => null,
            'retry'      => 3,
        ],
    ],

    'version' => [
        'driver'    => 'database',  // 'database' or 'csv'
        'table'     => 'reference_versions',
        'columns'   => ['version' => 'version', 'activated_at' => 'activated_at'],
        'csv_path'  => '',
        'cache_ttl' => 300,
    ],

    // Per-table overrides
    'tables' => [
        // 'products' => [
        //     'ttl' => ['record' => 7200],
        //     'chunk_size' => 10000,
        // ],
    ],
];
```

## Documentation

- [Cache Architecture](docs/cache-architecture.md) — Design details (TTL, indexes, self-healing, queue)
- [Overview (Japanese)](docs/overview-ja.md) — Structure and usage in Japanese
- [Laravel Builder Coverage](docs/laravel-builder-coverage.md) — API compatibility table

## License

MIT
