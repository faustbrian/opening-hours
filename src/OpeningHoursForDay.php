<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use ArrayAccess;
use ArrayIterator;
use Cline\OpeningHours\Exceptions\NonMutableOffsets;
use Cline\OpeningHours\Exceptions\OverlappingTimeRanges;
use Cline\OpeningHours\Helpers\Arr;
use Cline\OpeningHours\Helpers\DataTrait;
use Cline\OpeningHours\Helpers\RangeFinder;
use Countable;
use Generator;
use IteratorAggregate;
use Stringable;

use function array_any;
use function array_first;
use function array_reverse;
use function count;
use function implode;
use function is_array;
use function strcmp;
use function uasort;

/**
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class OpeningHoursForDay implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
    use DataTrait;
    use RangeFinder;

    private function __construct(
        /** @var array<TimeRange> */
        private array $openingHours,
        public mixed $data,
    ) {
        $this->guardAgainstTimeRangeOverlaps($openingHours);
    }

    public function __toString(): string
    {
        $values = [];

        foreach ($this->openingHours as $openingHour) {
            $values[] = (string) $openingHour;
        }

        return implode(',', $values);
    }

    public static function fromStrings(array $strings, mixed $data = null): static
    {
        if (isset($strings['hours'])) {
            return self::fromStrings($strings['hours'], $strings['data'] ?? $data);
        }

        $data ??= $strings['data'] ?? null;
        unset($strings['data']);

        uasort($strings, static fn ($a, $b): int => strcmp(self::getHoursFromRange($a), self::getHoursFromRange($b)));

        return new self(
            Arr::map($strings, static fn ($string): TimeRange => $string instanceof TimeRange ? $string : TimeRange::fromDefinition($string)),
            $data,
        );
    }

    public function isOpenAt(ComparableTime $time): bool
    {
        return array_any($this->openingHours, fn ($timeRange) => $timeRange->containsTime($time));
    }

    public function isOpenAtTheEndOfTheDay(): bool
    {
        return $this->isOpenAt(Time::fromString('23:59'));
    }

    public function isOpenAtNight(ComparableTime $time): bool
    {
        return array_any($this->openingHours, fn ($timeRange) => $timeRange->containsNightTime($time));
    }

    /**
     * @param  array<callable>     $filters
     * @return null|Time|TimeRange
     */
    public function openingHoursFilter(array $filters, bool $reverse = false): ?TimeDataContainer
    {
        foreach (($reverse ? array_reverse($this->openingHours) : $this->openingHours) as $timeRange) {
            foreach ($filters as $filter) {
                if ($result = $filter($timeRange)) {
                    return $result;
                }
            }
        }

        return null;
    }

    public function nextOpen(ComparableTime $time): ?Time
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?Time => $this->findOpenInFreeTime($time, $timeRange),
        ]);
    }

    public function nextOpenRange(ComparableTime $time): ?TimeRange
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?TimeRange => $this->findRangeInFreeTime($time, $timeRange),
        ]);
    }

    public function nextClose(ComparableTime $time): ?Time
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?Time => $this->findCloseInWorkingHours($time, $timeRange),
            fn (TimeRange $timeRange): ?Time => $this->findCloseInFreeTime($time, $timeRange),
        ]);
    }

    public function nextCloseRange(ComparableTime $time): ?TimeRange
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?TimeRange => $this->findCloseRangeInWorkingHours($time, $timeRange),
            fn (TimeRange $timeRange): ?TimeRange => $this->findRangeInFreeTime($time, $timeRange),
        ]);
    }

    public function previousOpen(ComparableTime $time): ?Time
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?Time => $this->findPreviousOpenInFreeTime($time, $timeRange),
            fn (TimeRange $timeRange): ?Time => $this->findOpenInWorkingHours($time, $timeRange),
        ], true);
    }

    public function previousOpenRange(ComparableTime $time): ?TimeRange
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?TimeRange => $this->findRangeInFreeTime($time, $timeRange),
        ], true);
    }

    public function previousClose(ComparableTime $time): ?Time
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?Time => $this->findPreviousCloseInWorkingHours($time, $timeRange),
        ], true);
    }

    public function previousCloseRange(ComparableTime $time): ?TimeRange
    {
        return $this->openingHoursFilter([
            fn (TimeRange $timeRange): ?TimeRange => $this->findPreviousRangeInFreeTime($time, $timeRange),
        ], true);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->openingHours[$offset]);
    }

    public function offsetGet($offset): TimeRange
    {
        return $this->openingHours[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        throw NonMutableOffsets::forClass(self::class);
    }

    public function offsetUnset($offset): void
    {
        throw NonMutableOffsets::forClass(self::class);
    }

    public function count(): int
    {
        return count($this->openingHours);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->openingHours);
    }

    /**
     * @return array<TimeRange>
     */
    public function forTime(ComparableTime $time): Generator
    {
        foreach ($this as $range) {
            /** @var TimeRange $range */
            if (!$range->containsTime($time)) {
                continue;
            }

            yield $range;
        }
    }

    /**
     * @return array<TimeRange>
     */
    public function forNightTime(ComparableTime $time): Generator
    {
        foreach ($this as $range) {
            /** @var TimeRange $range */
            if (!$range->containsNightTime($time)) {
                continue;
            }

            yield $range;
        }
    }

    public function isEmpty(): bool
    {
        return $this->openingHours === [];
    }

    public function map(callable $callback): array
    {
        return Arr::map($this->openingHours, $callback);
    }

    private static function getHoursFromRange($range): string
    {
        return (string) ((
            is_array($range)
            ? ($range['hours'] ?? array_first($range) ?? null)
            : null
        ) ?: $range);
    }

    private function guardAgainstTimeRangeOverlaps(array $openingHours): void
    {
        foreach (Arr::createUniquePairs($openingHours) as $timeRanges) {
            /** @var array{0: TimeRange, 1: TimeRange} $timeRanges */
            if ($timeRanges[0]->overlaps($timeRanges[1])) {
                throw OverlappingTimeRanges::forRanges($timeRanges[0], $timeRanges[1]);
            }
        }
    }
}
