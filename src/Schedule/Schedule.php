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
 * Resolves weekly schedules and date-based overrides into effective schedule state.
 *
 * This type sits one level below {@see \Cline\OpeningHours\OpeningHours} and owns the
 * package's core precedence rules. Matching overrides are evaluated before the baseline
 * weekly schedule, and optional query timezones are applied before any date comparison so
 * every caller observes consistent results.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class Schedule
{
    /**
     * @param WeeklySchedule     $weeklySchedule Baseline weekly schedule used when no override applies.
     * @param list<ScheduleRule> $rules
     */
    public function __construct(
        private WeeklySchedule $weeklySchedule,
        private array $rules = [],
    ) {}

    /**
     * Return the base weekly schedule used when no override matches the queried date.
     */
    public function weeklySchedule(): WeeklySchedule
    {
        return $this->weeklySchedule;
    }

    /**
     * Return the date-based override rules checked before the weekly schedule.
     *
     * @return list<ScheduleRule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Resolve the day schedule that applies on the given date.
     *
     * The input date is normalized through the optional query timezone first. The first
     * matching override wins; if no rule matches, the resolver falls back to the weekly
     * schedule entry for that weekday.
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
     * Determine whether the schedule is open at the given date-time.
     *
     * Overnight ranges from the previous day are included. That matters when a day closes
     * after midnight, because the current instant can be governed by yesterday's effective
     * schedule rather than today's first range.
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

    /**
     * Normalize an arbitrary date-time input into an immutable value in the query timezone.
     *
     * If no explicit timezone is provided, only mutability is normalized and the original
     * instant is preserved.
     */
    private function resolveDate(DateTimeInterface $date, ?QueryOptions $options): DateTimeImmutable
    {
        $resolved = DateTimeImmutable::createFromInterface($date);

        if ($options?->timezone instanceof DateTimeZone) {
            return $resolved->setTimezone($options->timezone);
        }

        return $resolved;
    }
}
