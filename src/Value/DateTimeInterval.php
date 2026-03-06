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
 * @author Brian Faust <brian@cline.sh>
 * Concrete date-time interval derived from local opening-hours ranges.
 *
 * @psalm-immutable
 */
final readonly class DateTimeInterval
{
    private function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {}

    /**
     * Creates an interval by anchoring a local time range to a schedule date.
     *
     * Overnight ranges end on the following calendar day.
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
     * Creates an interval around a reference moment for containment checks.
     *
     * For overnight ranges, the interval is placed on the previous or next day
     * relative to the reference as needed.
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
     * Returns the inclusive start of the interval.
     */
    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * Returns the exclusive end of the interval.
     */
    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Checks whether the date-time falls inside the interval.
     *
     * The start is inclusive and the end is exclusive.
     */
    public function contains(DateTimeInterface $dateTime): bool
    {
        $value = DateTimeImmutable::createFromInterface($dateTime);

        return $value >= $this->start && $value < $this->end;
    }
}
