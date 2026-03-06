<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Helpers;

use DateTimeInterface;

use function min;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait DiffTrait
{
    /**
     * Return the amount of open time (number of seconds as a floating number) between 2 dates/times.
     */
    public function diffInOpenSeconds(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        return $this->diffInSeconds('isClosedAt', 'nextClose', 'nextOpen', $startDate, $endDate);
    }

    /**
     * Return the amount of open time (number of minutes as a floating number) between 2 dates/times.
     */
    public function diffInOpenMinutes(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        return $this->diffInOpenSeconds($startDate, $endDate) / 60;
    }

    /**
     * Return the amount of open time (number of hours as a floating number) between 2 dates/times.
     */
    public function diffInOpenHours(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        return $this->diffInOpenMinutes($startDate, $endDate) / 60;
    }

    /**
     * Return the amount of closed time (number of seconds as a floating number) between 2 dates/times.
     */
    public function diffInClosedSeconds(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        return $this->diffInSeconds('isOpenAt', 'nextOpen', 'nextClose', $startDate, $endDate);
    }

    /**
     * Return the amount of closed time (number of minutes as a floating number) between 2 dates/times.
     */
    public function diffInClosedMinutes(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        return $this->diffInClosedSeconds($startDate, $endDate) / 60;
    }

    /**
     * Return the amount of closed time (number of hours as a floating number) between 2 dates/times.
     */
    public function diffInClosedHours(DateTimeInterface $startDate, DateTimeInterface $endDate): float
    {
        return $this->diffInClosedMinutes($startDate, $endDate) / 60;
    }

    private function diffInSeconds(
        string $stateCheckMethod,
        string $nextDateMethod,
        string $skipDateMethod,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
    ): float {
        $time = 0.0;

        if ($endDate < $startDate) {
            return -$this->diffInSeconds($stateCheckMethod, $nextDateMethod, $skipDateMethod, $endDate, $startDate);
        }

        $date = $startDate;

        while ($date < $endDate) {
            if ($this->{$stateCheckMethod}($date)) {
                $date = $this->{$skipDateMethod}($date, null, $endDate);

                continue;
            }

            $nextDate = min($endDate, $this->{$nextDateMethod}($date, null, $endDate));
            $time += ((float) $nextDate->format('U.u')) - ((float) $date->format('U.u'));
            $date = $nextDate;
        }

        return $time;
    }
}
