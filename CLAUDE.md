# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Katana** — A Laravel package that provides a QueryBuilder-compatible interface over APCu cache. Reference data is loaded once from DB or CSV, stored in APCu, and queried via a fluent API. Traversal is generator-based for low memory usage; lookups are accelerated via index trees.

Target: Laravel composer package (PHP ^8.2, Laravel ^11.x)

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpunit --filter testMethodName
vendor/bin/pint
vendor/bin/phpstan analyse
```

## APCu Key Structure

```
katana:{table}:ids                              # 全IDの配列（存在の正典）
katana:{table}:record:{id}                      # 1レコード（シリアライズ）

katana:{table}:idx:{col}_{val}                  # non-unique index → [id, ...]
katana:{table}:idx:{col}_{val}:{col2}_{val2}    # composite index（階層構造）→ [id, ...]
katana:{table}:uidx:{col}_{val}                 # unique index → id
```

- カラムと値の結合: `_`（アンダースコア）
- 階層（composite index）の区切り: `:`（コロン）

## Self-Healing Cache

`ids` キーが各テーブルキャッシュの存在を担保する。

```
クエリ実行
  └─ ids キーなし             → 全件リロード（DB/CSV から再取得）
  └─ ids キーあり
       └─ record:{id} なし   → そのIDだけ再取得して APCu に保存
```

APCu に evict されても自動復旧する。全件リロードは常に全件入れ直し（差分更新なし）。

## TTL

デフォルト TTL はパッケージ全体で設定し、テーブル単位でオーバーライド可能。

## Data Flow

```
DB / CSV
  └─ Generator で読み込み（省メモリ）
       └─ apcu_store で record・index・ids を一括書き込み
```

## Query Flow

```
ReferenceQueryBuilder::where(...)
  └─ index ヒット → APCu から ID セット取得 → record を fetch
  └─ index なし   → ids からジェネレーターで全走査 + フィルタ
```

## Architecture

- `src/`
  - `ReferenceQueryBuilder` — fluent API。`Illuminate\Database\Query\Builder` のメソッドシグネチャに準拠
  - `Index/` — unique / non-unique / composite インデックスの管理
  - `Store/` — APCu の read/write 抽象化（key 生成・TTL・healing を含む）
  - `Loader/` — DB・CSV などのデータソースから generator で読み込み、Store へ書き込む
  - `Support/` — ジェネレーターベースのカーソル・イテレーターユーティリティ
- `tests/` — src/ に対応した PHPUnit テスト

## Key Design Constraints

- 結果を複数返す操作は内部で配列に収集せず、必ずジェネレーターで返す
- インデックスにヒットしない `where` のみ全走査にフォールバック
- public API のメソッドシグネチャは `Illuminate\Database\Query\Builder` と揃える
