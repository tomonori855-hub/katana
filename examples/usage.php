<?php

/**
 * Katana 使い方サンプル
 *
 * このファイルは実行用ではなく、使い方のリファレンスです。
 */

// ============================================================================
// 1. バージョン解決とテーブル登録
// ============================================================================

// config/katana.php でバージョン解決を設定:
//
//   'version' => [
//       'driver'    => 'database',          // 'database' or 'csv'
//       'table'     => 'reference_versions',
//       'columns'   => ['version' => 'version', 'activated_at' => 'activated_at'],
//       'csv_path'  => '',                  // CSV driver の場合のパス
//       'cache_ttl' => 300,                 // APCu キャッシュ秒数（5分）
//   ],
//
// KatanaServiceProvider が VersionResolverInterface を自動バインドする。
// → DB/CSV への問い合わせは cache_ttl 間隔でキャッシュされる。

// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\DB;
use Katana\Contracts\VersionResolverInterface;
use Katana\KatanaManager;
use Katana\Loader\QueryBuilderLoader;
use Katana\Loader\VersionedCsvLoader;

class AppServiceProvider
{
    public function boot(KatanaManager $katana, VersionResolverInterface $resolver): void
    {
        // バージョン解決（起動時に1回だけ）
        // VersionResolverInterface は KatanaServiceProvider で config から自動バインド済み
        $version = $resolver->resolve() ?? 'v1.0.0';

        // --- DB テーブルの登録 ---
        $katana->register('products', new QueryBuilderLoader(
            query: DB::table('products'),
            columns: ['id' => 'int', 'name' => 'string', 'price' => 'int', 'category' => 'string', 'country' => 'string'],
            indexes: [
                ['columns' => ['category'], 'unique' => false],
                ['columns' => ['country'], 'unique' => false],
                ['columns' => ['country', 'category'], 'unique' => false],  // composite index
            ],
            version: $version,
        ));

        $katana->register('active_users', new QueryBuilderLoader(
            query: DB::table('users')->where('active', true),
            columns: ['id' => 'int', 'name' => 'string', 'email' => 'string', 'role' => 'string'],
            indexes: [
                ['columns' => ['role'], 'unique' => false],
                ['columns' => ['email'], 'unique' => true],
            ],
            version: $version,
        ));

        // --- CSV テーブルの登録 ---
        // ディレクトリ構成:
        //   storage/katana/
        //     data/countries.csv          ← id,version,code,name,...
        //     definitions/countries.csv   ← column,type
        //     indexes/countries.csv       ← columns,unique
        //
        // $katana->register('countries', new VersionedCsvLoader(
        //     basePath: storage_path('katana'),
        //     table: 'countries',
        //     resolver: new CsvVersionResolver(storage_path('katana/versions.csv')),
        // ));
    }
}

// ============================================================================
// 2. クエリ実行（Controller やどこからでも）
// ============================================================================

use Katana\Facades\Katana;

// --- 基本クエリ ---

// 全件取得
$products = Katana::table('products')->get();

// 条件付き
$jpProducts = Katana::table('products')
    ->where('country', 'JP')
    ->get();

// 比較演算子
$expensive = Katana::table('products')
    ->where('price', '>=', 1000)
    ->orderBy('price', 'desc')
    ->get();

// --- find: O(1) 直読み ---

$product = Katana::table('products')->find(42);

// --- first / value ---

$cheapest = Katana::table('products')
    ->orderBy('price')
    ->first();

$cheapestName = Katana::table('products')
    ->orderBy('price')
    ->value('name');

// --- 集約 ---

$count = Katana::table('products')->where('country', 'JP')->count();
$total = Katana::table('products')->sum('price');
$avg = Katana::table('products')->avg('price');
$min = Katana::table('products')->min('price');
$max = Katana::table('products')->max('price');

// --- pluck ---

$names = Katana::table('products')->pluck('name');           // ['Widget', 'Gadget', ...]
$nameById = Katana::table('products')->pluck('name', 'id');     // [1 => 'Widget', 2 => 'Gadget']

// --- whereIn / whereBetween ---

$selected = Katana::table('products')
    ->whereIn('category', ['electronics', 'books'])
    ->get();

$midRange = Katana::table('products')
    ->whereBetween('price', [500, 2000])
    ->get();

// --- whereNull ---

$active = Katana::table('products')
    ->whereNull('deleted_at')
    ->get();

// --- 複雑な WHERE（Closure でグループ化）---

// WHERE (country = 'JP' OR country = 'US') AND price >= 500
$result = Katana::table('products')
    ->where(function ($q) {
        $q->where('country', 'JP')
            ->orWhere('country', 'US');
    })
    ->where('price', '>=', 500)
    ->get();

// WHERE (country = 'JP' AND category = 'electronics')
//    OR (country = 'US' AND price < 1000)
$result = Katana::table('products')
    ->where(function ($q) {
        $q->where('country', 'JP')
            ->where('category', 'electronics');
    })
    ->orWhere(function ($q) {
        $q->where('country', 'US')
            ->where('price', '<', 1000);
    })
    ->get();

// WHERE NOT (category = 'discontinued')
$result = Katana::table('products')
    ->whereNot(function ($q) {
        $q->where('category', 'discontinued');
    })
    ->get();

// --- whereAny / whereNone ---

// WHERE (name = 'Widget' OR category = 'Widget')
$result = Katana::table('products')
    ->whereAny(['name', 'category'], 'Widget')
    ->get();

// --- whereFilter: PHP クロージャで任意条件 ---

$result = Katana::table('products')
    ->whereFilter(fn ($r) => str_starts_with($r['name'], 'A'))
    ->get();

// --- exists ---

$hasJP = Katana::table('products')->where('country', 'JP')->exists();

// --- ページネーション ---

$page = Katana::table('products')
    ->orderBy('name')
    ->paginate(perPage: 15, page: 1);

// --- cursor: 省メモリ Generator ---

foreach (Katana::table('products')->where('country', 'JP')->cursor() as $product) {
    // 1件ずつ処理（メモリに全件載せない）
}

// --- limit / offset ---

$top3 = Katana::table('products')
    ->orderBy('price', 'desc')
    ->limit(3)
    ->get();

// ============================================================================
// 3. DI で使う（Facade を使わない場合）
// ============================================================================

class ProductController
{
    public function index(KatanaManager $katana)
    {
        return $katana->table('products')
            ->where('country', 'JP')
            ->orderBy('price')
            ->get();
    }
}

// ============================================================================
// 4. Artisan コマンドでキャッシュウォームアップ
// ============================================================================

// 全テーブル rebuild（Loader のバージョンを使用）
// php artisan katana:rebuild

// 特定テーブルのみ
// php artisan katana:rebuild products active_users

// バージョンを明示して rebuild（デプロイ時に推奨）
// php artisan katana:rebuild --reference-version=v2.0.0

// デプロイスクリプト例:
//   php artisan katana:rebuild --reference-version=$(get_latest_version)
//   → Pod 起動直後の初回リクエスト前にキャッシュをウォーム

// ============================================================================
// 5. バージョンフロー
// ============================================================================

// 1. reference_versions テーブル（DB or CSV）
//    ┌──────────────────────────────────────────────┐
//    │ id │ version │ activated_at                       │
//    │  1 │ v1.0.0  │ 2024-01-01 00:00:00           │
//    │  2 │ v2.0.0  │ 2025-06-01 00:00:00 ← active  │
//    └──────────────────────────────────────────────┘
//
// 2. Pod 起動時
//    VersionResolver::resolve() → "v2.0.0"
//    ↓
//    Loader に version を渡して register
//    ↓
//    artisan katana:rebuild --reference-version=v2.0.0
//    ↓
//    APCu keys: katana:products:v2.0.0:ids
//               katana:products:v2.0.0:record:1
//               katana:products:v2.0.0:meta
//
// 3. リクエスト時
//    Client → X-Reference-Version: v2.0.0
//    ↓
//    Middleware で照合 → 一致 → キャッシュからクエリ実行
//    ↓
//    バージョン不一致 → X-Reference-Version-Mismatch: true
//
// 4. バージョンアップ時
//    reference_versions に v3.0.0 を INSERT（activated_at = 未来日時）
//    ↓
//    activated_at に達する → VersionResolver が v3.0.0 を返し始める
//    ↓
//    v2.0.0 のキーはヒットしなくなる → Self-Healing で v3.0.0 rebuild

// ============================================================================
// 6. Self-Healing
// ============================================================================

// rebuild を明示的に呼ばなくても、初回クエリ時に自動で Loader から読み込む。
// APCu が evict されても、次のクエリで自動復旧する。
// → 手動での rebuild は「ウォームアップ」用。通常運用では不要。
