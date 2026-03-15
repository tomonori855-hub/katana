# Review Specification

docs/cache-architecture.md の仕様を4人のレビュアーに並列でレビューさせてください。

## レビュアー

4つの Agent を **並列で** 起動してください。各 Agent には docs/cache-architecture.md を読ませ、以下の観点でレビューさせてください。

### Reviewer 1: アーキテクト（設計整合性）
- クラス間の依存関係に矛盾はないか
- 責務の分離は適切か（QueryBuilder / Processor / Repository / Store / Loader）
- interface の設計は拡張しやすいか
- 設計パターンの使い方に問題はないか

### Reviewer 2: インフラ/パフォーマンス（APCu・メモリ・速度）
- APCu の使い方に問題はないか（キーサイズ、値サイズ、TTL、eviction）
- メモリ使用量は現実的か（ids hashmap、index 構造、chunk）
- パフォーマンスのボトルネックはないか（binary search、intersection、full scan）
- Self-Healing のフローに無限ループやレースコンディションはないか

### Reviewer 3: 利用者目線（DX・運用）
- config の設計は使いやすいか
- Self-Healing は運用時に問題を起こさないか
- エラー時の挙動は利用者にとって理解しやすいか
- rebuild strategy の選択肢は十分か
- ドキュメントに曖昧な点や矛盾はないか

### Reviewer 4: 設計原則（SOLID・DRY・KISS・ド・モルガン）
- SOLID 原則（S/O/L/I/D）に違反していないか
- DRY/KISS/YAGNI の観点で問題はないか
- ド・モルガンの法則: 複合条件の否定が正しく変換されているか
- 関心の分離は適切か
- interface の契約は明確か

## 出力

各レビュアーの結果をまとめて、以下の形式で報告してください:

### 問題一覧（重要度順）
| # | 重要度 | レビュアー | 問題 | 対策案 |

重複する指摘はまとめてください。
