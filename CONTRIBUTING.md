# Contributing

## 開発環境

```bash
composer install
```

## コマンド

```bash
composer test        # PHPUnit
composer analyse     # PHPStan (level 8)
composer format      # Pint (Laravel preset)
composer check       # test + analyse
```

## コーディング規約

### 静的解析

- **PHPStan level 8** — `phpstan.neon` で設定済み
- **Pint** — Laravel preset + `not_operator_with_successor_space`（`pint.json`）

### PHP

- `in_array()` は必ず第3引数 `true`（strict mode）を指定する
- PHP 8.4: `fgetcsv` / `fputcsv` は `escape: ''` パラメータを指定する
- NULL の扱いは MySQL セマンティクスに準拠する

### テスト規約

- **Unit Tests**: AAA 形式（`// Arrange`, `// Act`, `// Assert`）
- **Feature Tests**: BDD/Gherkin 形式（`// Given`, `// When`, `// Then` コメント）
- **組み合わせテスト**: PHPUnit `#[DataProvider]`
- **モック**: Mockery（シンプルに、false positive/negative を避ける）
- **アサーションメッセージ**: 全アサーションに「何を検証し、何が期待結果か」のメッセージを付与
- **テスト用 Loader**: `tests/Support/InMemoryLoader` を使用
- **リスト型プロパティ**: `/** @var list<array<string, mixed>> */` を付与

### 命名規約

- "master" は使わない → "reference" を使用
- カラム名: `activated_at`（not `start_at`）
- ヘッダー: `X-Reference-Version`
