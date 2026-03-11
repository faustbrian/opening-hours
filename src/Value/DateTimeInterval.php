<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Value;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Concrete date-time interval derived from a local opening-hours range.
 *
 * The rest of the package stores availability in date-agnostic local values.
 * This type is the bridge to real calendar instants when callers need to check
 * containment against a `DateTimeInterface` or expose resolved openings as
 * actual datetimes. The interval keeps the package-wide convention of inclusive
 * start and exclusive end boundaries.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DateTimeInterval
{
    private function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {}

    /**
     * Anchor a local range to the calendar date that owns the schedule entry.
     *
     * This is used when a caller already knows which day's schedule applies and
     * wants real datetimes for each local range. Overnight ranges carry their
     * end boundary into the following day while keeping the original schedule
     * date as the opening boundary.
     */
    public static function fromScheduleDate(
        DateTimeInterface $scheduleDate,
        LocalTimeRange $range,
    ): self {
        $startOfDay = DateTimeImmutable::createFromInterface($scheduleDate)
            ->setTime(0, 0, 0, 0);
        $endOfDay = $range->wrapsToNextDay()
            ? $startOfDay->add(
                new DateInterval('P1D'),
            )
            : $startOfDay;

        return new self(
            $startOfDay->setTime($range->start()->hours(), $range->start()->minutes(), 0, 0),
            $endOfDay->setTime($range->end()->hours() % 24, $range->end()->minutes(), 0, 0),
        );
    }

    /**
     * Build an interval around a reference moment for "open now" style checks.
     *
     * Overnight ranges are shifted to the previous or following calendar day
     * relative to the reference so the interval covers the concrete occurrence
     * that could contain that moment. This avoids requiring callers to reason
     * about whether an after-midnight time belongs to today's or yesterday's
     * schedule entry.
     */
    public static function fromLocalTimeRange(
        DateTimeInterface $reference,
        LocalTimeRange $range,
    ): self {
        $referenceDay = DateTimeImmutable::createFromInterface($reference)
            ->setTime(0, 0, 0);

        $referenceMinutes = ((int) $reference->format('G') * 60) + (int) $reference->format('i');
        $endMinutes = $range->end()->minutesSinceMidnight();

        $startDay = $referenceDay;
        $endDay = $referenceDay;

        if ($range->wrapsToNextDay()) {
            if ($referenceMinutes < $endMinutes) {
                $startDay = $startDay->sub(
                    new DateInterval('P1D'),
                );
            } else {
                $endDay = $endDay->add(
                    new DateInterval('P1D'),
                );
            }
        }

        return new self(
            $startDay->setTime($range->start()->hours(), $range->start()->minutes(), 0),
            $endDay->setTime($range->end()->hours() % 24, $range->end()->minutes(), 0),
        );
    }

    /**
     * Get the inclusive opening instant for the resolved interval.
     */
    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Get the exclusive closing instant for the resolved interval.
     */
    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Determine whether a concrete moment falls inside the interval.
     *
     * The start is inclusive and the end is exclusive so adjacent intervals can
     * meet without both claiming the same instant.
     */
    public function contains(DateTimeInterface $dateTime): bool
    {
        $value = DateTimeImmutable::createFromInterface($dateTime);

        return $value >= $this->start && $value < $this->end;
    }
}
