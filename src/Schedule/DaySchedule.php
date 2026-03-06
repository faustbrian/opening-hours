<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Schedule;

use Cline\OpeningHours\Exceptions\DaySchedulesCannotContainOverlappingRanges;
use Cline\OpeningHours\Value\LocalTime;
use Cline\OpeningHours\Value\LocalTimeRange;
use Stringable;

use function array_any;
use function array_slice;
use function count;
use function implode;
use function usort;

/**
 * Immutable opening-hours schedule for a single day.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DaySchedule implements Stringable
{
    /**
     * @param list<LocalTimeRange> $ranges
     */
    private function __construct(
        private array $ranges,
    ) {}

    /**
     * Format the schedule as a comma-separated list of local time ranges.
     */
    public function __toString(): string
    {
        return implode(',', $this->ranges);
    }

    /**
     * Create a schedule with no opening ranges.
     */
    public static function closed(): self
    {
        return new self([]);
    }

    /**
     * Create a day schedule from one or more non-overlapping ranges.
     *
     * Ranges are normalized into start-time order before storage.
     *
     * @throws DaySchedulesCannotContainOverlappingRanges When any ranges overlap.
     */
    public static function fromRanges(LocalTimeRange ...$ranges): self
    {
        usort($ranges, static fn (LocalTimeRange $left, LocalTimeRange $right): int => $left->start()->minutesSinceMidnight() <=> $right->start()->minutesSinceMidnight());

        foreach ($ranges as $index => $range) {
            foreach (array_slice($ranges, $index + 1) as $other) {
                if ($range->overlaps($other)) {
                    throw DaySchedulesCannotContainOverlappingRanges::daySchedulesCannotContainOverlappingRanges();
                }
            }
        }

        return new self($ranges);
    }

    /**
     * @return list<LocalTimeRange>
     */
    public function ranges(): array
    {
        return $this->ranges;
    }

    /**
     * Determine whether the schedule has no opening ranges.
     */
    public function isClosed(): bool
    {
        return $this->ranges === [];
    }

    /**
     * Determine whether the given local time falls inside any opening range.
     */
    public function contains(LocalTime $time): bool
    {
        return array_any($this->ranges, fn ($range) => $range->contains($time));
    }

    /**
     * Determine whether any opening range continues past midnight.
     */
    public function carriesIntoNextDay(): bool
    {
        return array_any($this->ranges, fn ($range) => $range->wrapsToNextDay());
    }

    /**
     * Determine whether the schedule represents continuous opening from 00:00 to 24:00.
     */
    public function isAlwaysOpen(): bool
    {
        return count($this->ranges) === 1
            && $this->ranges[0]->format() === '00:00-24:00';
    }
}
