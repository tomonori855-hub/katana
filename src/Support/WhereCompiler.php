<?php

namespace Kura\Support;

/**
 * Compiles a where-condition list into a single Closure(array): bool.
 *
 * WhereEvaluator interprets the $wheres array on every record (N iterations).
 * WhereCompiler processes it once at query time, capturing column names,
 * operators, and values as PHP variables inside closures — eliminating
 * per-record match() dispatch and array key lookups.
 *
 * Usage:
 *   $predicate = WhereCompiler::compile($wheres);   // once
 *   foreach ($pks as $pk) {
 *       if ($predicate($record)) { ... }            // N times
 *   }
 */
final class WhereCompiler
{
    /**
     * @param  list<array<string, mixed>>  $wheres
     * @return \Closure(array<string, mixed>): bool
     */
    public static function compile(array $wheres): \Closure
    {
        if ($wheres === []) {
            return static fn (): bool => true;
        }

        if (count($wheres) === 1) {
            $pred = self::compileOne($wheres[0]);
            $negate = $wheres[0]['negate'] ?? false;

            return $negate
                ? static fn (array $r): bool => ! $pred($r)
                : $pred;
        }

        // Compile each condition into [pred, negate, boolean]
        $items = [];
        foreach ($wheres as $where) {
            $items[] = [
                'pred' => self::compileOne($where),
                'negate' => $where['negate'] ?? false,
                'boolean' => $where['boolean'] ?? 'and',
            ];
        }

        return static function (array $record) use ($items): bool {
            $pred = $items[0]['pred'];
            $result = $pred($record);
            if ($items[0]['negate']) {
                $result = ! $result;
            }

            $n = count($items);
            for ($i = 1; $i < $n; $i++) {
                $item = $items[$i];
                if ($item['boolean'] === 'and') {
                    if (! $result) {
                        return false;
                    }
                    $val = ($item['pred'])($record);
                    $result = $item['negate'] ? ! $val : $val;
                } else {
                    if ($result) {
                        return true;
                    }
                    $val = ($item['pred'])($record);
                    $result = $item['negate'] ? ! $val : $val;
                }
            }

            return $result;
        };
    }

    // =========================================================================
    // Per-type compilers
    // =========================================================================

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileOne(array $where): \Closure
    {
        return match ($where['type']) {
            'basic' => self::compileBasic($where),
            'in' => self::compileIn($where),
            'null' => self::compileNull($where),
            'between' => self::compileBetween($where),
            'betweenColumns' => self::compileBetweenColumns($where),
            'valueBetween' => self::compileValueBetween($where),
            'like' => self::compileLike($where),
            'column' => self::compileColumn($where),
            'rowValuesIn' => self::compileRowValuesIn($where),
            'nullsafe' => self::compileNullsafe($where),
            'filter' => $where['callback'],
            'nested' => self::compileNested($where),
            default => throw new \InvalidArgumentException("Unknown where type: {$where['type']}"),
        };
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileBasic(array $where): \Closure
    {
        $col = $where['column'];
        $op = $where['operator'];
        $val = $where['value'];

        // NULL value — only = and != have defined results; others → false
        if ($val === null) {
            return match ($op) {
                '=' => static fn ($r) => ($r[$col] ?? null) === null,
                '!=', '<>' => static fn ($r) => ($r[$col] ?? null) !== null,
                default => static fn (): bool => false,
            };
        }

        // Non-null value
        // For = and !=/<>: null actual behaves correctly via PHP comparison
        // For ordering/bitwise: null actual → false (DB semantics)
        return match ($op) {
            '=' => static fn ($r) => ($r[$col] ?? null) === $val,
            '!=', '<>' => static fn ($r) => ($r[$col] ?? null) !== $val,
            '>' => static fn ($r) => null !== ($v = $r[$col] ?? null) && $v > $val,
            '>=' => static fn ($r) => null !== ($v = $r[$col] ?? null) && $v >= $val,
            '<' => static fn ($r) => null !== ($v = $r[$col] ?? null) && $v < $val,
            '<=' => static fn ($r) => null !== ($v = $r[$col] ?? null) && $v <= $val,
            'like' => (static function () use ($col, $val): \Closure {
                $regex = self::likeToRegex($val, false);

                return static fn ($r) => null !== ($v = $r[$col] ?? null) && (bool) preg_match($regex, (string) $v);
            })(),
            'not like' => (static function () use ($col, $val): \Closure {
                $regex = self::likeToRegex($val, false);

                return static fn ($r) => null !== ($v = $r[$col] ?? null) && ! preg_match($regex, (string) $v);
            })(),
            '&' => static fn ($r) => null !== ($v = $r[$col] ?? null) && ((int) $v & (int) $val) !== 0,
            '|' => static fn ($r) => null !== ($v = $r[$col] ?? null) && ((int) $v | (int) $val) !== 0,
            '^' => static fn ($r) => null !== ($v = $r[$col] ?? null) && ((int) $v ^ (int) $val) !== 0,
            '<<' => static fn ($r) => null !== ($v = $r[$col] ?? null) && ((int) $v << (int) $val) !== 0,
            '>>' => static fn ($r) => null !== ($v = $r[$col] ?? null) && ((int) $v >> (int) $val) !== 0,
            '&~' => static fn ($r) => null !== ($v = $r[$col] ?? null) && ((int) $v & ~(int) $val) !== 0,
            '!&' => static fn ($r) => null !== ($v = $r[$col] ?? null) && ((int) $v & (int) $val) === 0,
            default => throw new \InvalidArgumentException("Unsupported operator: {$op}"),
        };
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileIn(array $where): \Closure
    {
        $col = $where['column'];
        $not = $where['not'];
        $valueSet = $where['valueSet'];

        if ($where['values'] === []) {
            return static fn (): bool => $not;
        }

        return $not
            ? static fn ($r) => null !== ($v = $r[$col] ?? null) && ! isset($valueSet[$v])
            : static fn ($r) => null !== ($v = $r[$col] ?? null) && isset($valueSet[$v]);
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileNull(array $where): \Closure
    {
        $col = $where['column'];
        $not = $where['not'];

        return $not
            ? static fn ($r) => ($r[$col] ?? null) !== null
            : static fn ($r) => ($r[$col] ?? null) === null;
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileBetween(array $where): \Closure
    {
        $col = $where['column'];
        $not = $where['not'];
        $min = $where['values'][0];
        $max = $where['values'][1];

        return $not
            ? static fn ($r) => null === ($v = $r[$col] ?? null) || ! ($v >= $min && $v <= $max)
            : static fn ($r) => null !== ($v = $r[$col] ?? null) && $v >= $min && $v <= $max;
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileBetweenColumns(array $where): \Closure
    {
        $col = $where['column'];
        $minCol = $where['values'][0];
        $maxCol = $where['values'][1];
        $not = $where['not'];

        return $not
            ? static function ($r) use ($col, $minCol, $maxCol): bool {
                $v = $r[$col] ?? null;
                $min = $r[$minCol] ?? null;
                $max = $r[$maxCol] ?? null;

                return $v === null || $min === null || $max === null || ! ($v >= $min && $v <= $max);
            }
        : static function ($r) use ($col, $minCol, $maxCol): bool {
            $v = $r[$col] ?? null;
            $min = $r[$minCol] ?? null;
            $max = $r[$maxCol] ?? null;

            return $v !== null && $min !== null && $max !== null && $v >= $min && $v <= $max;
        };
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileValueBetween(array $where): \Closure
    {
        $val = $where['value'];
        $minCol = $where['columns'][0];
        $maxCol = $where['columns'][1];
        $not = $where['not'];

        if ($val === null) {
            return static fn (): bool => (bool) $not;
        }

        return $not
            ? static function ($r) use ($val, $minCol, $maxCol): bool {
                $min = $r[$minCol] ?? null;
                $max = $r[$maxCol] ?? null;

                return $min === null || $max === null || ! ($val >= $min && $val <= $max);
            }
        : static function ($r) use ($val, $minCol, $maxCol): bool {
            $min = $r[$minCol] ?? null;
            $max = $r[$maxCol] ?? null;

            return $min !== null && $max !== null && $val >= $min && $val <= $max;
        };
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileLike(array $where): \Closure
    {
        $col = $where['column'];
        $not = $where['not'];
        $caseSensitive = $where['caseSensitive'];
        $regex = self::likeToRegex($where['value'], $caseSensitive);

        return $not
            ? static fn ($r) => null !== ($v = $r[$col] ?? null) && ! preg_match($regex, (string) $v)
            : static fn ($r) => null !== ($v = $r[$col] ?? null) && (bool) preg_match($regex, (string) $v);
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileColumn(array $where): \Closure
    {
        $left = $where['first'];
        $right = $where['second'];
        $op = $where['operator'];

        return match ($op) {
            '=' => static fn ($r) => ($r[$left] ?? null) === ($r[$right] ?? null),
            '!=', '<>' => static fn ($r) => ($r[$left] ?? null) !== ($r[$right] ?? null),
            '>' => static function ($r) use ($left, $right): bool {
                $l = $r[$left] ?? null;
                $rv = $r[$right] ?? null;

                return $l !== null && $rv !== null && $l > $rv;
            },
            '>=' => static function ($r) use ($left, $right): bool {
                $l = $r[$left] ?? null;
                $rv = $r[$right] ?? null;

                return $l !== null && $rv !== null && $l >= $rv;
            },
            '<' => static function ($r) use ($left, $right): bool {
                $l = $r[$left] ?? null;
                $rv = $r[$right] ?? null;

                return $l !== null && $rv !== null && $l < $rv;
            },
            '<=' => static function ($r) use ($left, $right): bool {
                $l = $r[$left] ?? null;
                $rv = $r[$right] ?? null;

                return $l !== null && $rv !== null && $l <= $rv;
            },
            default => throw new \InvalidArgumentException("Unsupported operator for whereColumn: {$op}"),
        };
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileRowValuesIn(array $where): \Closure
    {
        /** @var list<string> $columns */
        $columns = $where['columns'];
        $tupleSet = $where['tupleSet'];
        $not = $where['not'];

        return static function (array $r) use ($columns, $tupleSet, $not): bool {
            $parts = [];
            foreach ($columns as $col) {
                $v = $r[$col] ?? null;
                if ($v === null) {
                    return false;
                }
                $parts[] = (string) $v;
            }
            $in = isset($tupleSet[implode('|', $parts)]);

            return $not ? ! $in : $in;
        };
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileNullsafe(array $where): \Closure
    {
        $col = $where['column'];
        $val = $where['value'];

        return static fn ($r) => ($r[$col] ?? null) === $val;
    }

    /**
     * @param  array<string, mixed>  $where
     * @return \Closure(array<string, mixed>): bool
     */
    private static function compileNested(array $where): \Closure
    {
        return self::compile($where['wheres']);
    }

    // =========================================================================
    // Shared utilities
    // =========================================================================

    private static function likeToRegex(string $pattern, bool $caseSensitive): string
    {
        return '/^'.str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')).'$/'
            .($caseSensitive ? '' : 'i');
    }
}
