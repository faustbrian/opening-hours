<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class DateTimeRange
{
    public static function fromTimeRange(DateTimeInterface $date, TimeRange $timeRange, mixed $data = null): TimeRange
    {
        $start = $timeRange->start();
        $end = $timeRange->end();

        $referenceTime = $date->format(TimeDataContainer::TIME_FORMAT);
        $startDate = self::copyAndModify($date, $start.($start->format() > $referenceTime ? ' - 1 day' : ''));
        $endDate = self::copyAndModify($date, $end.($end->format() < $referenceTime ? ' + 1 day' : ''));

        return TimeRange::fromTimes(
            Time::fromString($start->format(), $start->data, $startDate),
            Time::fromString($end->format(), $start->data, $endDate),
            $data,
        );
    }

    private static function copyAndModify(DateTimeInterface $date, string $modifier): DateTimeInterface
    {
        if ($date instanceof DateTimeImmutable) {
            return $date->modify($modifier);
        }

        $copiedDate = clone $date;
        $copiedDate->modify($modifier);

        return $copiedDate;
    }
}
