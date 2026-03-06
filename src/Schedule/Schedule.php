<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Schedule;

use Cline\OpeningHours\Config\QueryOptions;
use Cline\OpeningHours\Rule\ScheduleRule;
use Cline\OpeningHours\Value\Day;
use Cline\OpeningHours\Value\LocalTime;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use function array_any;

/**
 * @author Brian Faust <brian@cline.sh>
 * Resolves effective opening-hours schedules for specific dates and times.
 *
 * @psalm-immutable
 */
final readonly class Schedule
{
    /**
     * @param list<ScheduleRule> $rules
     */
    public function __construct(
        private WeeklySchedule $weeklySchedule,
        private array $rules = [],
    ) {}

    /**
     * Returns the base weekly schedule used when no override matches.
     */
    public function weeklySchedule(): WeeklySchedule
    {
        return $this->weeklySchedule;
    }

    /**
     * Returns the date-based override rules applied before the weekly schedule.
     *
     * @return list<ScheduleRule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Returns the day schedule that applies on the given date.
     *
     * Matching override rules take precedence over the weekly schedule.
     */
    public function forDate(DateTimeInterface $date, ?QueryOptions $options = null): DaySchedule
    {
        $date = $this->resolveDate($date, $options);

        foreach ($this->rules as $rule) {
            if ($rule->appliesTo($date)) {
                return $rule->schedule();
            }
        }

        return $this->weeklySchedule->forDay(Day::onDateTime($date));
    }

    /**
     * Checks whether the schedule is open at the given date-time.
     *
     * Overnight ranges from the previous day are included.
     */
    public function isOpenAt(DateTimeInterface $dateTime, ?QueryOptions $options = null): bool
    {
        $dateTime = $this->resolveDate($dateTime, $options);
        $time = LocalTime::fromDateTime($dateTime);

        if ($this->forDate($dateTime, $options)->contains($time)) {
            return true;
        }

        $previousDate = $dateTime->sub(
            new DateInterval('P1D'),
        );

        return array_any($this->forDate($previousDate, $options)->ranges(), fn ($range): bool => $range->wrapsToNextDay()
        && $time->minutesSinceMidnight() < $range->end()->minutesSinceMidnight());
    }

    private function resolveDate(DateTimeInterface $date, ?QueryOptions $options): DateTimeImmutable
    {
        $resolved = DateTimeImmutable::createFromInterface($date);

        if ($options?->timezone instanceof DateTimeZone) {
            return $resolved->setTimezone($options->timezone);
        }

        return $resolved;
    }
}
