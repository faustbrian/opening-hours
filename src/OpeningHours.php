<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use Cline\OpeningHours\Config\QueryOptions;
use Cline\OpeningHours\Exceptions\DaySchedulesMustBeDefinedAsArrays;
use Cline\OpeningHours\Exceptions\ExceptionKeysMustBeStrings;
use Cline\OpeningHours\Exceptions\ExceptionsMustBeDefinedAsArray;
use Cline\OpeningHours\Exceptions\InvalidOpeningHoursDefinition;
use Cline\OpeningHours\Exceptions\TimeRangesMustBeStringsOrContainHoursKey;
use Cline\OpeningHours\Exceptions\UnsupportedExceptionKey;
use Cline\OpeningHours\Exceptions\UnsupportedScheduleKey;
use Cline\OpeningHours\Rule\DateOverrideRule;
use Cline\OpeningHours\Rule\DateRangeOverrideRule;
use Cline\OpeningHours\Rule\MonthDayOverrideRule;
use Cline\OpeningHours\Rule\ScheduleRule;
use Cline\OpeningHours\Schedule\DaySchedule;
use Cline\OpeningHours\Schedule\Schedule;
use Cline\OpeningHours\Schedule\WeeklySchedule;
use Cline\OpeningHours\SchemaOrg\SchemaOrgOpeningHoursFormatter;
use Cline\OpeningHours\SchemaOrg\SchemaOrgOpeningHoursParser;
use Cline\OpeningHours\Value\DateTimeInterval;
use Cline\OpeningHours\Value\Day;
use Cline\OpeningHours\Value\LocalTimeRange;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use function array_all;
use function array_key_exists;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function usort;

/**
 * @author Brian Faust <brian@cline.sh>
 * High-level API for querying weekly opening hours and date-specific overrides.
 *
 * @psalm-immutable
 */
final readonly class OpeningHours
{
    public const int DEFAULT_DAY_LIMIT = 8;

    private function __construct(
        private Schedule $schedule,
        private QueryOptions $defaultQueryOptions = new QueryOptions(),
    ) {}

    /**
     * @param list<ScheduleRule> $rules
     */
    public static function fromWeeklySchedule(
        WeeklySchedule $weeklySchedule,
        array $rules = [],
        ?QueryOptions $options = null,
    ): self {
        return new self(
            new Schedule($weeklySchedule, $rules),
            $options ?? new QueryOptions(),
        );
    }

    /**
     * Creates opening hours from the package's array definition format.
     *
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidOpeningHoursDefinition
     */
    public static function fromArray(array $definition, ?QueryOptions $options = null): self
    {
        self::guardAgainstUnsupportedDefinitionKeys($definition);

        $daySchedules = [];

        foreach (Day::cases() as $day) {
            $daySchedules[$day->value] = self::dayScheduleFromDefinition(
                $definition[$day->value] ?? [],
            );
        }

        $rules = [];
        $exceptions = $definition['exceptions'] ?? [];

        if (!is_array($exceptions)) {
            throw ExceptionsMustBeDefinedAsArray::exceptionsMustBeDefinedAsArray();
        }

        foreach ($exceptions as $key => $value) {
            if (!is_string($key)) {
                throw ExceptionKeysMustBeStrings::exceptionKeysMustBeStrings();
            }

            $schedule = self::dayScheduleFromDefinition($value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
                $rules[] = new DateOverrideRule($key, $schedule);

                continue;
            }

            if (preg_match('/^\d{2}-\d{2}$/', $key)) {
                $rules[] = new MonthDayOverrideRule($key, $schedule);

                continue;
            }

            if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/', $key, $matches)) {
                $rules[] = new DateRangeOverrideRule($matches[1], $matches[2], $schedule);

                continue;
            }

            throw UnsupportedExceptionKey::withKey($key);
        }

        return self::fromWeeklySchedule(
            WeeklySchedule::fromDaySchedules($daySchedules),
            $rules,
            $options,
        );
    }

    /**
     * Creates opening hours from a legacy array definition and merges any
     * overlapping local ranges before validation.
     *
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidOpeningHoursDefinition
     */
    public static function fromArrayAndMergeOverlappingRanges(
        array $definition,
        ?QueryOptions $options = null,
    ): self {
        return self::fromArray(
            self::mergeOverlappingRanges($definition),
            $options,
        );
    }

    /**
     * Normalize a legacy array definition into a strict package-compatible
     * structure and merge overlapping local ranges within each day.
     *
     * @param  array<array-key, mixed> $definition
     * @return array<string, mixed>
     */
    public static function mergeOverlappingRanges(array $definition): array
    {
        self::guardAgainstUnsupportedDefinitionKeys($definition);

        $normalized = [];

        foreach (Day::cases() as $day) {
            $normalized[$day->value] = self::normalizeMergedDayDefinition(
                $definition[$day->value] ?? [],
            );
        }

        $exceptions = $definition['exceptions'] ?? [];

        if (!is_array($exceptions)) {
            $normalized['exceptions'] = $exceptions;

            return $normalized;
        }

        $normalized['exceptions'] = [];

        foreach ($exceptions as $key => $value) {
            $normalized['exceptions'][$key] = self::normalizeMergedDayDefinition($value);
        }

        return $normalized;
    }

    /**
     * Creates opening hours from Schema.org `OpeningHoursSpecification` data.
     *
     * @param array<array-key, mixed>|string $structuredData
     *
     * @throws Exceptions\InvalidOpeningHoursSpecification
     */
    public static function createFromStructuredData(
        array|string $structuredData,
        ?QueryOptions $options = null,
    ): self {
        return new self(
            SchemaOrgOpeningHoursParser::parse($structuredData),
            $options ?? new QueryOptions(),
        );
    }

    /**
     * Returns the underlying resolved schedule model.
     */
    public function schedule(): Schedule
    {
        return $this->schedule;
    }

    /**
     * Returns the base weekly schedule without applying override rules.
     */
    public function weeklySchedule(): WeeklySchedule
    {
        return $this->schedule->weeklySchedule();
    }

    /**
     * @return list<ScheduleRule>
     */
    public function rules(): array
    {
        return $this->schedule->rules();
    }

    /**
     * Returns the effective schedule for the provided date.
     */
    public function forDate(DateTimeInterface $date, ?QueryOptions $options = null): DaySchedule
    {
        return $this->schedule->forDate($date, $this->resolveOptions($options));
    }

    /**
     * Checks whether the business is open at the given moment.
     */
    public function isOpenAt(DateTimeInterface $dateTime, ?QueryOptions $options = null): bool
    {
        return $this->schedule->isOpenAt($dateTime, $this->resolveOptions($options));
    }

    /**
     * Checks whether the business is closed at the given moment.
     */
    public function isClosedAt(DateTimeInterface $dateTime, ?QueryOptions $options = null): bool
    {
        return !$this->isOpenAt($dateTime, $options);
    }

    /**
     * Finds the next opening boundary after the given moment.
     *
     * Returns `null` when no matching boundary is found within the configured
     * search window.
     */
    public function nextOpen(DateTimeInterface $dateTime, ?QueryOptions $options = null): ?DateTimeImmutable
    {
        return $this->findBoundary($dateTime, $this->resolveOptions($options), true, true);
    }

    /**
     * Finds the next closing boundary after the given moment.
     *
     * Returns `null` when no matching boundary is found within the configured
     * search window.
     */
    public function nextClose(DateTimeInterface $dateTime, ?QueryOptions $options = null): ?DateTimeImmutable
    {
        $resolvedOptions = $this->resolveOptions($options);
        $resolvedDateTime = $this->resolveDateTime($dateTime, $resolvedOptions);

        foreach ($this->intervalsForDate($resolvedDateTime, $resolvedOptions) as $interval) {
            if ($interval->contains($resolvedDateTime)) {
                return $this->formatResult($interval->end(), $resolvedOptions);
            }
        }

        return $this->findBoundary($dateTime, $resolvedOptions, false, true);
    }

    /**
     * Finds the most recent opening boundary before the given moment.
     *
     * Returns `null` when no matching boundary is found within the configured
     * search window.
     */
    public function previousOpen(DateTimeInterface $dateTime, ?QueryOptions $options = null): ?DateTimeImmutable
    {
        return $this->findBoundary($dateTime, $this->resolveOptions($options), true, false);
    }

    /**
     * Finds the most recent closing boundary before the given moment.
     *
     * Returns `null` when no matching boundary is found within the configured
     * search window.
     */
    public function previousClose(DateTimeInterface $dateTime, ?QueryOptions $options = null): ?DateTimeImmutable
    {
        $resolvedOptions = $this->resolveOptions($options);
        $resolvedDateTime = $this->resolveDateTime($dateTime, $resolvedOptions);

        foreach ($this->intervalsForDate($resolvedDateTime, $resolvedOptions) as $interval) {
            if ($interval->contains($resolvedDateTime)) {
                return $this->formatResult($interval->start(), $resolvedOptions);
            }
        }

        return $this->findBoundary($dateTime, $resolvedOptions, false, false);
    }

    /**
     * Formats the schedule as Schema.org `OpeningHoursSpecification` items.
     *
     * @return list<array<string, string>>
     */
    public function asStructuredData(): array
    {
        return SchemaOrgOpeningHoursFormatter::format($this->schedule);
    }

    /**
     * Returns `true` when the weekly schedule is entirely closed and there are
     * no override rules.
     */
    public function isAlwaysClosed(): bool
    {
        if ($this->rules() !== []) {
            return false;
        }

        return array_all($this->weeklySchedule()->days(), fn ($daySchedule) => $daySchedule->isClosed());
    }

    /**
     * Returns `true` when the weekly schedule is open all day every day and
     * there are no override rules.
     */
    public function isAlwaysOpen(): bool
    {
        if ($this->rules() !== []) {
            return false;
        }

        return array_all($this->weeklySchedule()->days(), fn ($daySchedule) => $daySchedule->isAlwaysOpen());
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    private static function guardAgainstUnsupportedDefinitionKeys(array $definition): void
    {
        $supportedKeys = [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
            'exceptions',
        ];

        foreach (array_keys($definition) as $key) {
            if (!is_string($key)) {
                continue;
            }

            if (!in_array($key, $supportedKeys, true)) {
                throw UnsupportedScheduleKey::withKey($key);
            }
        }
    }

    private static function dayScheduleFromDefinition(mixed $definition): DaySchedule
    {
        if ($definition === [] || $definition === null) {
            return DaySchedule::closed();
        }

        if (is_array($definition) && array_key_exists('hours', $definition)) {
            $definition = $definition['hours'];
        }

        if (!is_array($definition)) {
            throw DaySchedulesMustBeDefinedAsArrays::daySchedulesMustBeDefinedAsArrays();
        }

        if ($definition === []) {
            return DaySchedule::closed();
        }

        return DaySchedule::fromRanges(
            ...array_map(
                self::rangeFromDefinition(...),
                $definition,
            ),
        );
    }

    private static function rangeFromDefinition(mixed $definition): LocalTimeRange
    {
        if (is_string($definition)) {
            return LocalTimeRange::fromString($definition);
        }

        if (is_array($definition) && is_string($definition['hours'] ?? null)) {
            return LocalTimeRange::fromString($definition['hours']);
        }

        throw TimeRangesMustBeStringsOrContainHoursKey::timeRangesMustBeStringsOrContainHoursKey();
    }

    /**
     * @return list<string>
     */
    private static function normalizeMergedDayDefinition(mixed $definition): array
    {
        $ranges = self::flattenRangeDefinitions($definition);

        if ($ranges === []) {
            return [];
        }

        usort(
            $ranges,
            static fn (LocalTimeRange $left, LocalTimeRange $right): int => $left->start()->minutesSinceMidnight() <=> $right->start()->minutesSinceMidnight(),
        );

        $merged = [];

        foreach ($ranges as $range) {
            $lastIndex = array_key_last($merged);

            if ($lastIndex === null) {
                $merged[] = $range;

                continue;
            }

            $lastRange = $merged[$lastIndex];

            if (!$lastRange->overlaps($range)) {
                $merged[] = $range;

                continue;
            }

            $merged[$lastIndex] = self::mergeLocalTimeRanges($lastRange, $range);
        }

        return array_values(array_map(
            static fn (LocalTimeRange $range): string => $range->format(),
            $merged,
        ));
    }

    /**
     * @return list<LocalTimeRange>
     */
    private static function flattenRangeDefinitions(mixed $definition): array
    {
        if ($definition === null || $definition === []) {
            return [];
        }

        if (is_string($definition)) {
            return [LocalTimeRange::fromString($definition)];
        }

        if (!is_array($definition)) {
            throw DaySchedulesMustBeDefinedAsArrays::daySchedulesMustBeDefinedAsArrays();
        }

        if (array_key_exists('hours', $definition)) {
            return self::flattenRangeDefinitions($definition['hours']);
        }

        return array_merge(
            ...array_map(
                self::flattenRangeDefinitions(...),
                $definition,
            ),
        );
    }

    private static function mergeLocalTimeRanges(
        LocalTimeRange $left,
        LocalTimeRange $right,
    ): LocalTimeRange {
        $start = $left->start()->format();

        $end = $left->end()->minutesSinceMidnight() >= $right->end()->minutesSinceMidnight()
            ? $left->end()->format()
            : $right->end()->format();

        return LocalTimeRange::fromString($start.'-'.$end);
    }

    /**
     * @return list<DateTimeInterval>
     */
    private function intervalsForDate(DateTimeImmutable $date, QueryOptions $options): array
    {
        $startOfDay = $date->setTime(0, 0, 0, 0);
        $intervals = [];
        $previousDate = $startOfDay->sub(
            new DateInterval('P1D'),
        );

        foreach ($this->schedule->forDate($previousDate, $options)->ranges() as $range) {
            if (!$range->wrapsToNextDay()) {
                continue;
            }

            $interval = DateTimeInterval::fromScheduleDate($previousDate, $range);

            if ($interval->end() <= $startOfDay) {
                continue;
            }

            $intervals[] = $interval;
        }

        foreach ($this->schedule->forDate($startOfDay, $options)->ranges() as $range) {
            $intervals[] = DateTimeInterval::fromScheduleDate($startOfDay, $range);
        }

        usort($intervals, static fn (DateTimeInterval $left, DateTimeInterval $right): int => $left->start() <=> $right->start());

        return $intervals;
    }

    private function findBoundary(
        DateTimeInterface $dateTime,
        QueryOptions $options,
        bool $searchOpen,
        bool $forward,
    ): ?DateTimeImmutable {
        $cursor = $this->resolveDateTime($dateTime, $options);

        for ($offset = 0; $offset <= $options->maxDaysToSearch; ++$offset) {
            $day = $forward
                ? $cursor->modify(sprintf('+%d day', $offset))
                : $cursor->modify(sprintf('-%d day', $offset));

            foreach ($this->intervalsForDate($day, $options) as $interval) {
                $boundary = $searchOpen ? $interval->start() : $interval->end();

                if ($forward && $boundary > $cursor) {
                    return $this->formatResult($boundary, $options);
                }

                if ($forward) {
                    continue;
                }

                if ($boundary >= $cursor) {
                    continue;
                }

                $candidate = $this->formatResult($boundary, $options);

                $latest ??= $candidate;

                if ($candidate <= $latest) {
                    continue;
                }

                $latest = $candidate;
            }
        }

        return $latest ?? null;
    }

    private function resolveOptions(?QueryOptions $options = null): QueryOptions
    {
        return $this->defaultQueryOptions->withOverrides($options);
    }

    private function resolveDateTime(DateTimeInterface $dateTime, QueryOptions $options): DateTimeImmutable
    {
        $resolved = DateTimeImmutable::createFromInterface($dateTime);

        if ($options->timezone instanceof DateTimeZone) {
            return $resolved->setTimezone($options->timezone);
        }

        return $resolved;
    }

    private function formatResult(DateTimeImmutable $dateTime, QueryOptions $options): DateTimeImmutable
    {
        if ($options->outputTimezone instanceof DateTimeZone) {
            return $dateTime->setTimezone($options->outputTimezone);
        }

        return $dateTime;
    }
}
