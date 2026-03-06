<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Helpers;

use Cline\OpeningHours\ComparableTime;
use Cline\OpeningHours\Time;
use Cline\OpeningHours\TimeRange;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait RangeFinder
{
    protected function findRangeInFreeTime(ComparableTime $time, TimeRange $timeRange): ?TimeRange
    {
        return $time->isBefore($timeRange->start()) ? $timeRange : null;
    }

    protected function findOpenInFreeTime(ComparableTime $time, TimeRange $timeRange): ?Time
    {
        return $this->findRangeInFreeTime($time, $timeRange)?->start();
    }

    protected function findOpenRangeInWorkingHours(ComparableTime $time, TimeRange $timeRange): ?TimeRange
    {
        return $time->isAfter($timeRange->start()) ? $timeRange : null;
    }

    protected function findOpenInWorkingHours(ComparableTime $time, TimeRange $timeRange): ?Time
    {
        return $this->findOpenRangeInWorkingHours($time, $timeRange)?->start();
    }

    protected function findCloseInWorkingHours(ComparableTime $time, TimeRange $timeRange): ?Time
    {
        return $timeRange->containsTime($time) ? $timeRange->end() : null;
    }

    protected function findCloseRangeInWorkingHours(ComparableTime $time, TimeRange $timeRange): ?TimeRange
    {
        return $timeRange->containsTime($time) ? $timeRange : null;
    }

    protected function findCloseInFreeTime(ComparableTime $time, TimeRange $timeRange): ?Time
    {
        return $this->findRangeInFreeTime($time, $timeRange)?->end();
    }

    protected function findPreviousRangeInFreeTime(ComparableTime $time, TimeRange $timeRange): ?TimeRange
    {
        return $time->isAfter($timeRange->end()) && $time->isAfter($timeRange->start()) ? $timeRange : null;
    }

    protected function findPreviousOpenInFreeTime(ComparableTime $time, TimeRange $timeRange): ?Time
    {
        return $this->findPreviousRangeInFreeTime($time, $timeRange)?->start();
    }

    protected function findPreviousCloseInWorkingHours(ComparableTime $time, TimeRange $timeRange): ?Time
    {
        $end = $timeRange->end();

        return $time->isAfter($end) ? $end : null;
    }
}
