<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc_Parser\Printer;

use function array_reverse;
use function count;
use Exception;
/**
 * Inspired by https://github.com/nikic/PHP-Parser/tree/36a6dcd04e7b0285e8f0868f44bd4927802f7df1
 *
 * Copyright (c) 2011, Nikita Popov
 * All rights reserved.
 *
 * Implements the Myers diff algorithm.
 *
 * Myers, Eugene W. "An O (ND) difference algorithm and its variations."
 * Algorithmica 1.1 (1986): 251-266.
 *
 * @template T
 * @internal
 */
class Differ
{
    /** @var callable(T, T): bool */
    private $is_equal;
    /**
     * Create differ over the given equality relation.
     *
     * @param callable(T, T): bool $isEqual Equality relation
     */
    public function __construct(callable $is_equal)
    {
        $this->is_equal = $is_equal;
    }
    /**
     * Calculate diff (edit script) from $old to $new.
     *
     * @param T[] $old Original array
     * @param T[] $new New array
     *
     * @return DiffElem[] Diff (edit script)
     */
    public function diff(array $old, array $new): array
    {
        [$trace, $x, $y] = $this->calculate_trace($old, $new);
        return $this->extract_diff($trace, $x, $y, $old, $new);
    }
    /**
     * Calculate diff, including "replace" operations.
     *
     * If a sequence of remove operations is followed by the same number of add operations, these
     * will be coalesced into replace operations.
     *
     * @param T[] $old Original array
     * @param T[] $new New array
     *
     * @return DiffElem[] Diff (edit script), including replace operations
     */
    public function diff_with_replacements(array $old, array $new): array
    {
        return $this->coalesce_replacements($this->diff($old, $new));
    }
    /**
     * @param T[] $old
     * @param T[] $new
     * @return array{array<int, array<int, int>>, int, int}
     */
    private function calculate_trace(array $old, array $new): array
    {
        $n = count($old);
        $m = count($new);
        $max = $n + $m;
        $v = [1 => 0];
        $trace = [];
        for ($d = 0; $d <= $max; $d++) {
            $trace[] = $v;
            for ($k = -$d; $k <= $d; $k += 2) {
                if ($k === -$d || $k !== $d && $v[$k - 1] < $v[$k + 1]) {
                    $x = $v[$k + 1];
                } else {
                    $x = $v[$k - 1] + 1;
                }
                $y = $x - $k;
                while ($x < $n && $y < $m && ($this->is_equal)($old[$x], $new[$y])) {
                    $x++;
                    $y++;
                }
                $v[$k] = $x;
                if ($x >= $n && $y >= $m) {
                    return [$trace, $x, $y];
                }
            }
        }
        throw new Exception('Should not happen');
    }
    /**
     * @param array<int, array<int, int>> $trace
     * @param T[] $old
     * @param T[] $new
     * @return DiffElem[]
     */
    private function extract_diff(array $trace, int $x, int $y, array $old, array $new): array
    {
        $result = [];
        for ($d = count($trace) - 1; $d >= 0; $d--) {
            $v = $trace[$d];
            $k = $x - $y;
            if ($k === -$d || $k !== $d && $v[$k - 1] < $v[$k + 1]) {
                $prev_k = $k + 1;
            } else {
                $prev_k = $k - 1;
            }
            $prev_x = $v[$prev_k];
            $prev_y = $prev_x - $prev_k;
            while ($x > $prev_x && $y > $prev_y) {
                $result[] = new Diff_Elem(Diff_Elem::TYPE_KEEP, $old[$x - 1], $new[$y - 1]);
                $x--;
                $y--;
            }
            if ($d === 0) {
                break;
            }
            while ($x > $prev_x) {
                $result[] = new Diff_Elem(Diff_Elem::TYPE_REMOVE, $old[$x - 1], null);
                $x--;
            }
            while ($y > $prev_y) {
                $result[] = new Diff_Elem(Diff_Elem::TYPE_ADD, null, $new[$y - 1]);
                $y--;
            }
        }
        return array_reverse($result);
    }
    /**
     * Coalesce equal-length sequences of remove+add into a replace operation.
     *
     * @param DiffElem[] $diff
     * @return DiffElem[]
     */
    private function coalesce_replacements(array $diff): array
    {
        $new_diff = [];
        $c = count($diff);
        for ($i = 0; $i < $c; $i++) {
            $diff_type = $diff[$i]->type;
            if ($diff_type !== Diff_Elem::TYPE_REMOVE) {
                $new_diff[] = $diff[$i];
                continue;
            }
            $j = $i;
            while ($j < $c && $diff[$j]->type === Diff_Elem::TYPE_REMOVE) {
                $j++;
            }
            $k = $j;
            while ($k < $c && $diff[$k]->type === Diff_Elem::TYPE_ADD) {
                $k++;
            }
            if ($j - $i === $k - $j) {
                $len = $j - $i;
                for ($n = 0; $n < $len; $n++) {
                    $new_diff[] = new Diff_Elem(Diff_Elem::TYPE_REPLACE, $diff[$i + $n]->old, $diff[$j + $n]->new);
                }
            } else {
                for (; $i < $k; $i++) {
                    $new_diff[] = $diff[$i];
                }
            }
            $i = $k - 1;
        }
        return $new_diff;
    }
}