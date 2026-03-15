<?php

namespace Katana\Facades;

use Illuminate\Support\Facades\Facade;
use Katana\KatanaManager;

/**
 * @method static void register(string $table, \Katana\Loader\LoaderInterface $loader, string $primaryKey = 'id')
 * @method static \Katana\ReferenceQueryBuilder table(string $table)
 * @method static \Katana\CacheRepository repository(string $table)
 * @method static void rebuild(string $table)
 * @method static void rebuildAll()
 * @method static list<string> registeredTables()
 *
 * @see KatanaManager
 */
class Katana extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return KatanaManager::class;
    }
}
