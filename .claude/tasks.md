# Katana Tasks

## 必須（公開前）

- [x] **README.md** — インストール・基本使い方・API一覧
- [x] **composer.json scripts** — `composer test` / `composer analyse` / `composer format` / `composer check`
- [x] **GitHub Actions CI** — `.github/workflows/tests.yml`（pint + phpstan + phpunit）
- [x] **TTL per-table 実装** — KatanaManager::rebuild() で tableConfigs からマージ済み
- [x] **KatanaServiceProvider 実装** — StoreInterface / VersionResolver / KatanaManager バインド + Facade

## 重要（品質・開発者体験）

- [x] **Loader 実装クラス** — CsvLoader / EloquentLoader / QueryBuilderLoader / VersionedCsvLoader
- [x] **コーディング規約ドキュメント** — CONTRIBUTING.md
- [x] **phpmd を require-dev に追加** — phpmd/phpmd ^2.15
- [x] **CHANGELOG.md** — Unreleased エントリ

## 任意

- [x] **`CacheQueryBuilder` 削除** — 未リリースのため deprecated ではなく完全削除。テストもリネーム済み
- [x] **`docs/` 整理** — `laravel-builder-coverage.md` 更新済み（whereRowValuesIn 追加、Architecture 更新）。公開
