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
 * Immutable opening-hours schedule for one resolved calendar day.
 *
 * Stores a day's local opening ranges in start-time order and enforces the invariant
 * that those ranges never overlap. That gives higher-level resolvers a compact,
 * trustworthy representation they can query for containment, carry-over behavior, and
 * always-open shortcuts without revalidating every range.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DaySchedule implements Stringable
{
    /**
     * @param list<LocalTimeRange> $ranges Non-overlapping local ranges sorted by start time.
     */
    private function __construct(
        private array $ranges,
    ) {}

    /**
     * Format the schedule as a comma-separated list of local time ranges.
     *
     * This mirrors the package's compact human-readable representation and is useful for
     * diagnostics, debugging, and snapshot-style assertions.
     */
    public function __toString(): string
    {
        return implode(',', $this->ranges);
    }

    /**
     * Create a schedule that remains closed for the entire day.
     */
    public static function closed(): self
    {
        return new self([]);
    }

    /**
     * Create a day schedule from one or more non-overlapping ranges.
     *
     * Ranges are normalized into start-time order before storage so all downstream
     * operations can assume a predictable representation. Any overlap is rejected because
     * overlapping ranges would make containment and boundary calculations ambiguous.
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
     * Return the day's opening ranges in normalized storage order.
     *
     * @return list<LocalTimeRange>
     */
    public function ranges(): array
    {
        return $this->ranges;
    }

    /**
     * Determine whether the schedule has no opening ranges at all.
     */
    public function isClosed(): bool
    {
        return $this->ranges === [];
    }

    /**
     * Determine whether the given local time falls inside any opening range.
     *
     * Range containment semantics come from {@see LocalTimeRange}, including support for
     * ranges that wrap past midnight.
     */
    public function contains(LocalTime $time): bool
    {
        return array_any($this->ranges, fn ($range) => $range->contains($time));
    }

    /**
     * Determine whether any opening range continues past midnight.
     *
     * Higher-level schedule queries use this to decide whether a previous day's schedule
     * can affect the next calendar date.
     */
    public function carriesIntoNextDay(): bool
    {
        return array_any($this->ranges, fn ($range) => $range->wrapsToNextDay());
    }

    /**
     * Determine whether the schedule represents continuous opening from 00:00 to 24:00.
     *
     * This intentionally recognizes only the package's canonical all-day representation,
     * which is a single range spanning the full local day.
     */
    public function isAlwaysOpen(): bool
    {
        return count($this->ranges) === 1
            && $this->ranges[0]->format() === '00:00-24:00';
    }
}
