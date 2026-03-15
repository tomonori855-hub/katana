# Review: Architect

docs/cache-architecture.md をアーキテクト視点でレビューしてください。

Agent を1つ起動し、docs/cache-architecture.md を読ませて以下の観点でレビューさせてください。

## 観点

- クラス間の依存関係に矛盾はないか
- 責務の分離は適切か（QueryBuilder / Processor / Repository / Store / Loader）
- 関心を分離し、正しく依存方向を制御しているか
- 凝集度/結合度の指標でモジュールを評価
- interface の設計は拡張しやすいか
- 設計パターンの使い方に問題はないか
- SOLID 原則に違反していないか
- データフローに不整合はないか

## 出力形式

| # | 重要度（重大/中/軽微） | 問題 | 対策案 |
