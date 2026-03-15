# Review: Infrastructure / Performance

docs/cache-architecture.md をインフラ・パフォーマンス視点でレビューしてください。

Agent を1つ起動し、docs/cache-architecture.md を読ませて以下の観点でレビューさせてください。

## 観点

- APCu の使い方に問題はないか（キーサイズ、値サイズ、TTL、eviction）
- メモリ使用量は現実的か（ids hashmap、index 構造、chunk）
- パフォーマンスのボトルネックはないか（binary search、intersection、full scan）
- Self-Healing のフローに無限ループやレースコンディションはないか
- 並行アクセス時の問題はないか
- APCu のシリアライズ/デシリアライズコストは許容範囲か

## 出力形式

| # | 重要度（重大/中/軽微） | 問題 | 対策案 |
