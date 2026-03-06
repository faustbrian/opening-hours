<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Helpers;

use const ARRAY_FILTER_USE_BOTH;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function is_array;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Arr
{
    public static function filter(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function map(array $array, callable $callback): array
    {
        $keys = array_keys($array);

        $items = array_map($callback, $array, $keys);

        return array_combine($keys, $items);
    }

    public static function flatMap(array $array, callable $callback): array
    {
        $mapped = self::map($array, $callback);

        $flattened = [];

        foreach ($mapped as $item) {
            if (is_array($item)) {
                $flattened = array_merge($flattened, $item);
            } else {
                $flattened[] = $item;
            }
        }

        return $flattened;
    }

    public static function pull(array &$array, string $key, mixed $default = null): mixed
    {
        $value = $array[$key] ?? $default;

        unset($array[$key]);

        return $value;
    }

    public static function createUniquePairs(array $array): array
    {
        $pairs = [];

        while ($a = array_shift($array)) {
            foreach ($array as $b) {
                $pairs[] = [$a, $b];
            }
        }

        return $pairs;
    }
}
