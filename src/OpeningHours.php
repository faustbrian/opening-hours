<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use Cline\OpeningHours\Exceptions\InvalidDate;
use Cline\OpeningHours\Exceptions\InvalidDateRange;
use Cline\OpeningHours\Exceptions\InvalidDateTimeClass;
use Cline\OpeningHours\Exceptions\InvalidTimezone;
use Cline\OpeningHours\Exceptions\MaximumLimitExceeded;
use Cline\OpeningHours\Exceptions\SearchLimitReached;
use Cline\OpeningHours\Helpers\Arr;
use Cline\OpeningHours\Helpers\DataTrait;
use Cline\OpeningHours\Helpers\DateTimeCopier;
use Cline\OpeningHours\Helpers\DiffTrait;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use Illuminate\Support\Facades\Date;
use Throwable;

use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function ceil;
use function count;
use function end;
use function explode;
use function implode;
use function in_array;
use function is_a;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function iterator_to_array;
use function json_encode;
use function key;
use function mb_trim;
use function preg_match;
use function preg_split;
use function sprintf;
use function ucfirst;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class OpeningHours
{
    use DataTrait;
    use DateTimeCopier;
    use DiffTrait;

    public const int DEFAULT_DAY_LIMIT = 8;

    public readonly mixed $data;

    /** @var array<OpeningHoursForDay> */
    private array $openingHours = [];

    /** @var array<OpeningHoursForDay> */
    private array $exceptions = [];

    /** @var array<callable> */
    private array $filters = [];

    private ?DateTimeZone $timezone = null;

    private ?DateTimeZone $outputTimezone = null;

    /** @var bool Allow for overflowing time ranges which overflow into the next day */
    private bool $overflow = false;

    /** @var null|int Number of days to try before abandoning the search of the next close/open time */
    private ?int $dayLimit = null;

    /** @var string Class of new date instances used for now, nextOpen, nextClose */
    private string $dateTimeClass = DateTime::class;

    private function __construct(
        array $data,
        string|DateTimeZone|null $timezone = null,
        string|DateTimeZone|null $outputTimezone = null,
    ) {
        $this->setTimezone($timezone);
        $this->setOutputTimezone($outputTimezone);

        $days = Day::cases();

        $timezones = array_key_exists('timezone', $data) ? $data['timezone'] : [];
        unset($data['timezone']);

        if (!is_array($timezones)) {
            $timezones = ['input' => $timezones];
        }

        if (array_key_exists('input', $timezones)) {
            $this->timezone = $this->parseTimezone($timezones['input']);
        }

        if (array_key_exists('output', $timezones)) {
            $this->outputTimezone = $this->parseTimezone($timezones['output']);
        }

        [$openingHours, $exceptions, $metaData, $filters, $overflow, $dateTimeClass] = $this
            ->parseOpeningHoursAndExceptions($data);

        $this->overflow = $overflow;

        $this->openingHours = array_combine(
            array_map(static fn (Day $day) => $day->value, $days),
            array_map(fn (Day $day): OpeningHoursForDay => $this->getOpeningHoursFromStrings($openingHours[$day->value] ?? []), $days),
        );

        $this->setExceptionsFromStrings($exceptions);
        $this->data = $metaData;
        $this->filters = $filters;

        if ($dateTimeClass !== null && !is_a($dateTimeClass, DateTimeInterface::class, true)) {
            throw InvalidDateTimeClass::forString($dateTimeClass);
        }

        $this->dateTimeClass = $dateTimeClass ?? DateTime::class;
    }

    /**
     * @param  array{
     *             monday?: array<array|string>,
     *             tuesday?: array<array|string>,
     *             wednesday?: array<array|string>,
     *             thursday?: array<array|string>,
     *             friday?: array<array|string>,
     *             saturday?: array<array|string>,
     *             sunday?: array<array|string>,
     *             exceptions?: array<array<array|string>>,
     *             filters?: array<callable>,
     *             overflow?: bool,
     *             data?: mixed,
     *             dateTimeClass?: class-string,
     *         }                         $data
     */
    public static function create(
        array $data,
        string|DateTimeZone|null $timezone = null,
        string|DateTimeZone|null $outputTimezone = null,
    ): self {
        return new self($data, $timezone, $outputTimezone);
    }

    public static function createFromStructuredData(
        array|string $structuredData,
        string|DateTimeZone|null $timezone = null,
        string|DateTimeZone|null $outputTimezone = null,
    ): self {
        return new self(
            array_merge(
                // https://schema.org/OpeningHoursSpecification allows overflow by default
                ['overflow' => true],
                OpeningHoursSpecificationParser::create($structuredData)->getOpeningHours(),
            ),
            $timezone,
            $outputTimezone,
        );
    }

    /**
     * @param array $data         hours definition array or sub-array
     * @param bool  $ignoreData   should ignore data
     * @param array $excludedKeys keys to ignore from parsing
     */
    public static function mergeOverlappingRanges(array $data, bool $ignoreData = true, array $excludedKeys = ['data', 'dateTimeClass', 'filters', 'overflow']): array
    {
        $result = [];
        $ranges = [];

        foreach (self::filterHours($data, $excludedKeys) as $key => [$value, $data]) {
            $dataString = $ignoreData ? '' : json_encode($data);

            $value = is_array($value)
                ? self::mergeOverlappingRanges($value, $ignoreData)
                : (is_string($value) ? TimeRange::fromString($value, $data) : $value);

            if ($value instanceof TimeRange) {
                $newRanges = [];

                foreach (($ranges[$dataString] ?? []) as $range) {
                    if ($value->format() === $range->format()) {
                        continue 2;
                    }

                    if ($value->overlaps($range) || $range->overlaps($value)) {
                        $value = TimeRange::fromList([$value, $range], $data);

                        continue;
                    }

                    $newRanges[] = $range;
                }

                $newRanges[] = $value;
                $ranges[$dataString] = $newRanges;

                continue;
            }

            $result[$key] = $value;
        }

        foreach ($ranges as $range) {
            foreach ($range as $rangeItem) {
                $result[] = $rangeItem;
            }
        }

        return $result;
    }

    /**
     * @param  array{
     *             monday?: array<array|string>,
     *             tuesday?: array<array|string>,
     *             wednesday?: array<array|string>,
     *             thursday?: array<array|string>,
     *             friday?: array<array|string>,
     *             saturday?: array<array|string>,
     *             sunday?: array<array|string>,
     *             exceptions?: array<array<array|string>>,
     *             filters?: array<callable>,
     *             overflow?: bool,
     *         }                         $data
     */
    public static function createAndMergeOverlappingRanges(array $data, string|DateTimeZone|null $timezone = null, string|DateTimeZone|null $outputTimezone = null, bool $ignoreData = true): self
    {
        return self::create(self::mergeOverlappingRanges($data, $ignoreData), $timezone, $outputTimezone);
    }

    public static function isValid(array $data): bool
    {
        try {
            self::create($data);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Set the number of days to try before abandoning the search of the next close/open time.
     *
     * @param  int   $dayLimit number of days
     * @return $this
     */
    public function setDayLimit(int $dayLimit): self
    {
        $this->dayLimit = $dayLimit;

        return $this;
    }

    /**
     * Get the number of days to try before abandoning the search of the next close/open time.
     */
    public function getDayLimit(): int
    {
        return $this->dayLimit ?: self::DEFAULT_DAY_LIMIT;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function forWeek(): array
    {
        return $this->openingHours;
    }

    public function forWeekCombined(): array
    {
        $equalDays = [];
        $allOpeningHours = $this->openingHours;
        $uniqueOpeningHours = array_unique($allOpeningHours);
        $nonUniqueOpeningHours = $allOpeningHours;

        foreach ($uniqueOpeningHours as $day => $value) {
            $equalDays[$day] = ['days' => [$day], 'opening_hours' => $value];
            unset($nonUniqueOpeningHours[$day]);
        }

        foreach ($uniqueOpeningHours as $uniqueDay => $uniqueValue) {
            foreach ($nonUniqueOpeningHours as $nonUniqueDay => $nonUniqueValue) {
                if ((string) $uniqueValue !== (string) $nonUniqueValue) {
                    continue;
                }

                $equalDays[$uniqueDay]['days'][] = $nonUniqueDay;
            }
        }

        return $equalDays;
    }

    public function forWeekConsecutiveDays(): array
    {
        $concatenatedDays = [];
        $allOpeningHours = $this->openingHours;

        foreach ($allOpeningHours as $day => $value) {
            $previousDay = end($concatenatedDays);

            if ($previousDay && (string) $previousDay['opening_hours'] === (string) $value) {
                $key = key($concatenatedDays);
                $concatenatedDays[$key]['days'][] = $day;

                continue;
            }

            $concatenatedDays[$day] = [
                'opening_hours' => $value,
                'days' => [$day],
            ];
        }

        return $concatenatedDays;
    }

    public function forDay(Day|string $day): OpeningHoursForDay
    {
        return $this->openingHours[$this->normalizeDayName($day)];
    }

    public function forDate(DateTimeInterface $date): OpeningHoursForDay
    {
        $date = $this->applyTimezone($date);

        foreach ($this->filters as $filter) {
            $result = $filter($date);

            if (is_array($result)) {
                return OpeningHoursForDay::fromStrings($result);
            }
        }

        return $this->exceptions[$date->format('Y-m-d')]
            ?? $this->exceptions[$date->format('m-d')]
            ?? $this->forDay(Day::onDateTime($date));
    }

    /**
     * @return array<TimeRange>
     */
    public function forDateTime(DateTimeInterface $date): array
    {
        $date = $this->applyTimezone($date);

        return array_merge(
            iterator_to_array($this->forDate(
                $this->yesterday($date),
            )->forNightTime(Time::fromDateTime($date))),
            iterator_to_array($this->forDate($date)->forTime(Time::fromDateTime($date))),
        );
    }

    public function exceptions(): array
    {
        return $this->exceptions;
    }

    public function isOpenOn(string $day): bool
    {
        if (preg_match('/^(?:(\d+)-)?(\d{1,2})-(\d{1,2})$/', $day, $match)) {
            [, $year, $month, $day] = $match;
            $year = $year ?: Date::now()->format('Y');

            return count($this->forDate(
                new DateTimeImmutable(sprintf('%s-%s-%s', $year, $month, $day), $this->timezone),
            )) > 0;
        }

        return count($this->forDay($day)) > 0;
    }

    public function isClosedOn(string $day): bool
    {
        return !$this->isOpenOn($day);
    }

    public function isOpenAt(DateTimeInterface $dateTime): bool
    {
        $dateTime = $this->applyTimezone($dateTime);

        if ($this->overflow) {
            $dateTimeMinus1Day = $this->yesterday($dateTime);
            $openingHoursForDayBefore = $this->forDate($dateTimeMinus1Day);

            if ($openingHoursForDayBefore->isOpenAtNight(PreciseTime::fromDateTime($dateTimeMinus1Day))) {
                return true;
            }
        }

        $openingHoursForDay = $this->forDate($dateTime);

        return $openingHoursForDay->isOpenAt(PreciseTime::fromDateTime($dateTime));
    }

    public function isClosedAt(DateTimeInterface $dateTime): bool
    {
        return !$this->isOpenAt($dateTime);
    }

    public function isOpen(): bool
    {
        return $this->isOpenAt(
            new $this->dateTimeClass(),
        );
    }

    public function isClosed(): bool
    {
        return $this->isClosedAt(
            new $this->dateTimeClass(),
        );
    }

    public function currentOpenRange(DateTimeInterface $dateTime): ?TimeRange
    {
        $dateTime = $this->applyTimezone($dateTime);
        $list = $this->forDateTime($dateTime);
        $range = end($list);

        return $range ? DateTimeRange::fromTimeRange($dateTime, $range) : null;
    }

    public function currentOpenRangeStart(DateTimeInterface $dateTime): ?DateTimeInterface
    {
        $outputTimezone = $this->getOutputTimezone($dateTime);
        $dateTime = $this->applyTimezone($dateTime);

        /** @var TimeRange $range */
        $range = $this->currentOpenRange($dateTime);

        if (!$range) {
            return null;
        }

        $dateTime = $this->copyDateTime($dateTime);

        $nextDateTime = $range->start()->toDateTime();

        if ($range->overflowsNextDay() && $nextDateTime->format('Hi') > $dateTime->format('Hi')) {
            $dateTime = $dateTime->modify('-1 day');
        }

        return $this->getDateWithTimezone(
            $dateTime->setTime((int) $nextDateTime->format('G'), (int) $nextDateTime->format('i'), 0),
            $outputTimezone,
        );
    }

    public function currentOpenRangeEnd(DateTimeInterface $dateTime): ?DateTimeInterface
    {
        $outputTimezone = $this->getOutputTimezone($dateTime);
        $dateTime = $this->applyTimezone($dateTime);

        /** @var TimeRange $range */
        $range = $this->currentOpenRange($dateTime);

        if (!$range) {
            return null;
        }

        $dateTime = $this->copyDateTime($dateTime);

        $nextDateTime = $range->end()->toDateTime();

        if ($range->overflowsNextDay() && $nextDateTime->format('Hi') < $dateTime->format('Hi')) {
            $dateTime = $dateTime->modify('+1 day');
        }

        return $this->getDateWithTimezone(
            $dateTime->setTime((int) $nextDateTime->format('G'), (int) $nextDateTime->format('i'), 0),
            $outputTimezone,
        );
    }

    public function nextOpen(
        ?DateTimeInterface $dateTime = null,
        ?DateTimeInterface $searchUntil = null,
        ?DateTimeInterface $cap = null,
    ): DateTimeInterface {
        $outputTimezone = $this->getOutputTimezone($dateTime);
        $dateTime = $this->applyTimezone($dateTime ?? new $this->dateTimeClass());
        $dateTime = $this->copyDateTime($dateTime);

        $openingHoursForDay = $this->forDate($dateTime);
        $nextOpen = $openingHoursForDay->nextOpen(PreciseTime::fromDateTime($dateTime));
        $tries = $this->getDayLimit();

        while (!$nextOpen instanceof Time || $nextOpen->hours() >= 24) {
            if (--$tries < 0) {
                throw MaximumLimitExceeded::forString(
                    'No open date/time found in the next '.$this->getDayLimit().' days,'.
                    ' use $openingHours->setDayLimit() to increase the limit.',
                );
            }

            $dateTime = $dateTime
                ->modify('+1 day')
                ->setTime(0, 0, 0);

            if ($this->isOpenAt($dateTime) && !$openingHoursForDay->isOpenAtTheEndOfTheDay()) {
                return $this->getDateWithTimezone($dateTime, $outputTimezone);
            }

            if ($cap instanceof DateTimeInterface && $dateTime > $cap) {
                return $cap;
            }

            if ($searchUntil instanceof DateTimeInterface && $dateTime > $searchUntil) {
                throw SearchLimitReached::forDate($searchUntil);
            }

            $openingHoursForDay = $this->forDate($dateTime);

            $nextOpen = $openingHoursForDay->nextOpen(PreciseTime::fromDateTime($dateTime));
        }

        if ($dateTime->format(TimeDataContainer::TIME_FORMAT) === TimeDataContainer::MIDNIGHT
            && $this->isOpenAt($this->copyAndModify($dateTime, '-1 second'))
        ) {
            return $this->getDateWithTimezone(
                $this->nextOpen($dateTime->modify('+1 second')),
                $outputTimezone,
            );
        }

        $nextDateTime = $nextOpen->toDateTime();

        return $this->getDateWithTimezone(
            $dateTime->setTime((int) $nextDateTime->format('G'), (int) $nextDateTime->format('i'), 0),
            $outputTimezone,
        );
    }

    public function nextClose(
        ?DateTimeInterface $dateTime = null,
        ?DateTimeInterface $searchUntil = null,
        ?DateTimeInterface $cap = null,
    ): DateTimeInterface {
        $outputTimezone = $this->getOutputTimezone($dateTime);
        $dateTime = $this->applyTimezone($dateTime ?? new $this->dateTimeClass());
        $dateTime = $this->copyDateTime($dateTime);

        $openRangeEnd = $this->currentOpenRange($dateTime)?->end();

        if ($openRangeEnd instanceof Time && $openRangeEnd->hours() < 24) {
            return $openRangeEnd->date() ?? $openRangeEnd->toDateTime($dateTime);
        }

        $nextClose = null;

        if ($this->overflow) {
            $dateTimeMinus1Day = $this->yesterday($dateTime);
            $openingHoursForDayBefore = $this->forDate($dateTimeMinus1Day);

            if ($openingHoursForDayBefore->isOpenAtNight(PreciseTime::fromDateTime($dateTimeMinus1Day))) {
                $nextClose = $openingHoursForDayBefore->nextClose(PreciseTime::fromDateTime($dateTime));
            }
        }

        $openingHoursForDay = $this->forDate($dateTime);

        if (!$nextClose instanceof Time) {
            $nextClose = $openingHoursForDay->nextClose(PreciseTime::fromDateTime($dateTime));

            if (
                $nextClose instanceof Time
                && $nextClose->hours() < 24
                && (
                    $nextClose->format('Gi') < $dateTime->format('Gi')
                    || ($this->isClosedAt($dateTime) && $this->nextOpen($dateTime)->format('Gi') > $nextClose->format('Gi'))
                )
            ) {
                $dateTime = $dateTime->modify('+1 day');
            }
        }

        $tries = $this->getDayLimit();

        while (!$nextClose instanceof Time || $nextClose->hours() >= 24) {
            if (--$tries < 0) {
                throw MaximumLimitExceeded::forString(
                    'No close date/time found in the next '.$this->getDayLimit().' days,'.
                    ' use $openingHours->setDayLimit() to increase the limit.',
                );
            }

            $dateTime = $dateTime
                ->modify('+1 day')
                ->setTime(0, 0, 0);

            if ($this->isClosedAt($dateTime) && $openingHoursForDay->isOpenAtTheEndOfTheDay()) {
                return $this->getDateWithTimezone($dateTime, $outputTimezone);
            }

            if ($cap instanceof DateTimeInterface && $dateTime > $cap) {
                return $cap;
            }

            if ($searchUntil instanceof DateTimeInterface && $dateTime > $searchUntil) {
                throw SearchLimitReached::forDate($searchUntil);
            }

            $openingHoursForDay = $this->forDate($dateTime);

            $nextClose = $openingHoursForDay->nextClose(PreciseTime::fromDateTime($dateTime));
        }

        $nextDateTime = $nextClose->toDateTime();

        return $this->getDateWithTimezone(
            $dateTime->setTime((int) $nextDateTime->format('G'), (int) $nextDateTime->format('i'), 0),
            $outputTimezone,
        );
    }

    public function previousOpen(
        DateTimeInterface $dateTime,
        ?DateTimeInterface $searchUntil = null,
        ?DateTimeInterface $cap = null,
    ): DateTimeInterface {
        $outputTimezone = $this->getOutputTimezone($dateTime);
        $dateTime = $this->copyDateTime($this->applyTimezone($dateTime));
        $openingHoursForDay = $this->forDate($dateTime);
        $previousOpen = $openingHoursForDay->previousOpen(PreciseTime::fromDateTime($dateTime));
        $tries = $this->getDayLimit();

        while (!$previousOpen instanceof Time || ($previousOpen->hours() === 0 && $previousOpen->minutes() === 0)) {
            if (--$tries < 0) {
                throw MaximumLimitExceeded::forString(
                    'No open date/time found in the previous '.$this->getDayLimit().' days,'.
                    ' use $openingHours->setDayLimit() to increase the limit.',
                );
            }

            $midnight = $dateTime->setTime(0, 0, 0);
            $dateTime = $this->copyAndModify($midnight, '-1 second');

            $openingHoursForDay = $this->forDate($dateTime);

            if ($this->isOpenAt($midnight) && !$openingHoursForDay->isOpenAtTheEndOfTheDay()) {
                return $this->getDateWithTimezone($midnight, $outputTimezone);
            }

            if ($cap instanceof DateTimeInterface && $dateTime < $cap) {
                return $cap;
            }

            if ($searchUntil instanceof DateTimeInterface && $dateTime < $searchUntil) {
                throw SearchLimitReached::forDate($searchUntil);
            }

            $previousOpen = $openingHoursForDay->previousOpen(PreciseTime::fromDateTime($dateTime));
        }

        $nextDateTime = $previousOpen->toDateTime();

        return $this->getDateWithTimezone(
            $dateTime->setTime((int) $nextDateTime->format('G'), (int) $nextDateTime->format('i'), 0),
            $outputTimezone,
        );
    }

    public function previousClose(
        DateTimeInterface $dateTime,
        ?DateTimeInterface $searchUntil = null,
        ?DateTimeInterface $cap = null,
    ): DateTimeInterface {
        $outputTimezone = $this->getOutputTimezone($dateTime);
        $dateTime = $this->copyDateTime($this->applyTimezone($dateTime));
        $previousClose = null;

        if ($this->overflow) {
            $dateTimeMinus1Day = $this->yesterday($dateTime);
            $openingHoursForDayBefore = $this->forDate($dateTimeMinus1Day);

            if ($openingHoursForDayBefore->isOpenAtNight(PreciseTime::fromDateTime($dateTimeMinus1Day))) {
                $previousClose = $openingHoursForDayBefore->previousClose(PreciseTime::fromDateTime($dateTime));
            }
        }

        $openingHoursForDay = $this->forDate($dateTime);

        if (!$previousClose instanceof Time) {
            $previousClose = $openingHoursForDay->previousClose(PreciseTime::fromDateTime($dateTime));
        }

        $tries = $this->getDayLimit();

        while (!$previousClose instanceof Time || ($previousClose->hours() === 0 && $previousClose->minutes() === 0)) {
            if (--$tries < 0) {
                throw MaximumLimitExceeded::forString(
                    'No close date/time found in the previous '.$this->getDayLimit().' days,'.
                    ' use $openingHours->setDayLimit() to increase the limit.',
                );
            }

            $midnight = $dateTime->setTime(0, 0, 0);
            $dateTime = $this->copyAndModify($midnight, '-1 second');
            $openingHoursForDay = $this->forDate($dateTime);

            if ($this->isClosedAt($midnight) && $openingHoursForDay->isOpenAtTheEndOfTheDay()) {
                return $this->getDateWithTimezone($midnight, $outputTimezone);
            }

            if ($cap instanceof DateTimeInterface && $dateTime < $cap) {
                return $cap;
            }

            if ($searchUntil instanceof DateTimeInterface && $dateTime < $searchUntil) {
                throw SearchLimitReached::forDate($searchUntil);
            }

            $previousClose = $openingHoursForDay->previousClose(PreciseTime::fromDateTime($dateTime));
        }

        $previousDateTime = $previousClose->toDateTime();

        return $this->getDateWithTimezone(
            $dateTime->setTime((int) $previousDateTime->format('G'), (int) $previousDateTime->format('i'), 0),
            $outputTimezone,
        );
    }

    public function regularClosingDays(): array
    {
        return array_keys($this->filter(
            static fn (OpeningHoursForDay $openingHoursForDay): bool => $openingHoursForDay->isEmpty(),
        ));
    }

    public function regularClosingDaysISO(): array
    {
        return array_map(
            static fn (string $dayName) => Day::from($dayName)->toISO(),
            $this->regularClosingDays(),
        );
    }

    public function exceptionalClosingDates(): array
    {
        $dates = array_keys($this->filterExceptions(
            static fn (OpeningHoursForDay $openingHoursForDay): bool => $openingHoursForDay->isEmpty(),
        ));

        return Arr::map($dates, static fn ($date) => DateTime::createFromFormat('Y-m-d', $date));
    }

    public function setTimezone(string|DateTimeZone|null $timezone): void
    {
        $this->timezone = $this->parseTimezone($timezone);
    }

    public function setOutputTimezone(string|DateTimeZone|null $timezone): void
    {
        $this->outputTimezone = $this->parseTimezone($timezone);
    }

    /**
     * Returns opening hours for the days that match a given condition as an array.
     *
     * @return array<OpeningHoursForDay>
     */
    public function filter(callable $callback): array
    {
        return Arr::filter($this->openingHours, $callback);
    }

    public function map(callable $callback): array
    {
        return Arr::map($this->openingHours, $callback);
    }

    public function flatMap(callable $callback): array
    {
        return Arr::flatMap($this->openingHours, $callback);
    }

    /**
     * Returns opening hours for the exceptions that match a given condition as an array.
     *
     * @return array<OpeningHoursForDay>
     */
    public function filterExceptions(callable $callback): array
    {
        return Arr::filter($this->exceptions, $callback);
    }

    /**
     * Checks that all exceptions match a given condition
     */
    public function everyExceptions(callable $callback): bool
    {
        return $this->filterExceptions(
            static fn (OpeningHoursForDay $day): bool => !$callback($day),
        ) === [];
    }

    public function mapExceptions(callable $callback): array
    {
        return Arr::map($this->exceptions, $callback);
    }

    public function flatMapExceptions(callable $callback): array
    {
        return Arr::flatMap($this->exceptions, $callback);
    }

    /**
     * Checks that opening hours for every day of the week matches a given condition
     */
    public function every(callable $callback): bool
    {
        return $this->filter(
            static fn (OpeningHoursForDay $day): bool => !$callback($day),
        ) === [];
    }

    public function asStructuredData(
        string $format = TimeDataContainer::TIME_FORMAT,
        DateTimeZone|string|null $timezone = null,
    ): array {
        $regularHours = $this->flatMap(
            static fn (OpeningHoursForDay $openingHoursForDay, string $day): array => $openingHoursForDay->map(
                static fn (TimeRange $timeRange): array => [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ucfirst($day),
                    'opens' => $timeRange->start()->format($format, $timezone),
                    'closes' => $timeRange->end()->format($format, $timezone),
                ],
            ),
        );

        $exceptions = $this->flatMapExceptions(
            static function (OpeningHoursForDay $openingHoursForDay, string $date) use ($format, $timezone): array {
                if ($openingHoursForDay->isEmpty()) {
                    $zero = Time::fromString(TimeDataContainer::MIDNIGHT)->format($format, $timezone);

                    return [[
                        '@type' => 'OpeningHoursSpecification',
                        'opens' => $zero,
                        'closes' => $zero,
                        'validFrom' => $date,
                        'validThrough' => $date,
                    ]];
                }

                return $openingHoursForDay->map(
                    static fn (TimeRange $timeRange): array => [
                        '@type' => 'OpeningHoursSpecification',
                        'opens' => $timeRange->start()->format($format, $timezone),
                        'closes' => $timeRange->end()->format($format, $timezone),
                        'validFrom' => $date,
                        'validThrough' => $date,
                    ],
                );
            },
        );

        return array_merge($regularHours, $exceptions);
    }

    public function isAlwaysClosed(): bool
    {
        $isAlwaysClosedCallback = static fn (OpeningHoursForDay $day): bool => $day->isEmpty();
        $allExceptionsAlwaysClosed = $this->everyExceptions($isAlwaysClosedCallback);
        $allOpeningHoursAlwaysClosed = $this->every($isAlwaysClosedCallback);
        $noFiltersApplied = $this->filters === [];

        return $allExceptionsAlwaysClosed && $noFiltersApplied && $allOpeningHoursAlwaysClosed;
    }

    public function isAlwaysOpen(): bool
    {
        $isAlwaysOpenCallback = static fn (OpeningHoursForDay $day): bool => ((string) $day) === '00:00-24:00';
        $allExceptionsAlwaysOpen = $this->everyExceptions($isAlwaysOpenCallback);
        $allOpeningHoursAlwaysOpen = $this->every($isAlwaysOpenCallback);
        $noFiltersApplied = $this->filters === [];

        return $allExceptionsAlwaysOpen && $noFiltersApplied && $allOpeningHoursAlwaysOpen;
    }

    private static function filterHours(array $data, array $excludedKeys): Generator
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $excludedKeys, true)) {
                continue;
            }

            if (is_int($key) && is_array($value) && isset($value['hours'])) {
                foreach ((array) $value['hours'] as $subKey => $hour) {
                    yield sprintf('%d.%s', $key, $subKey) => [$hour, $value['data'] ?? null];
                }

                continue;
            }

            yield $key => [$value, null];
        }
    }

    private function parseOpeningHoursAndExceptions(array $data): array
    {
        $dateTimeClass = Arr::pull($data, 'dateTimeClass');
        $metaData = Arr::pull($data, 'data');
        $overflow = (bool) Arr::pull($data, 'overflow', false);
        [$exceptions, $filters] = $this->parseExceptions(
            Arr::pull($data, 'exceptions', []),
            Arr::pull($data, 'filters', []),
        );
        $openingHours = $this->parseDaysOfWeeks($data);

        return [$openingHours, $exceptions, $metaData, $filters, $overflow, $dateTimeClass];
    }

    private function parseExceptions(array $data, array $filters): array
    {
        $exceptions = [];

        foreach ($data as $key => $exception) {
            if (is_callable($exception)) {
                $filters[] = $exception;

                continue;
            }

            foreach ($this->readDatesRange($key) as $date) {
                if (isset($exceptions[$date])) {
                    throw InvalidDateRange::invalidDateRange($key, $date);
                }

                $exceptions[$date] = $exception;
            }
        }

        return [$exceptions, $filters];
    }

    private function parseDaysOfWeeks(array $data): array
    {
        $openingHours = [];

        foreach ($data as $dayKey => $openingHoursData) {
            foreach ($this->readDatesRange($dayKey) as $rawDay) {
                $day = $this->normalizeDayName($rawDay);

                if (isset($openingHours[$day])) {
                    throw InvalidDateRange::invalidDateRange($dayKey, $day);
                }

                $openingHours[$day] = $openingHoursData;
            }
        }

        return $openingHours;
    }

    private function readDatesRange(Day|string $key): iterable
    {
        if ($key instanceof Day) {
            return [$key->value];
        }

        $toChunks = preg_split('/\sto\s/', $key, 2);

        if (count($toChunks) === 2) {
            return $this->daysBetween(mb_trim($toChunks[0]), mb_trim($toChunks[1]));
        }

        $dashChunks = explode('-', $key);
        $chunksCount = count($dashChunks);
        $firstChunk = mb_trim($dashChunks[0]);

        if ($chunksCount === 2 && preg_match('/^[A-Za-z]+$/', $firstChunk)) {
            return $this->daysBetween($firstChunk, mb_trim($dashChunks[1]));
        }

        if ($chunksCount >= 4) {
            $middle = (int) ceil($chunksCount / 2);

            return $this->daysBetween(
                mb_trim(implode('-', array_slice($dashChunks, 0, $middle))),
                mb_trim(implode('-', array_slice($dashChunks, $middle))),
            );
        }

        return [$key];
    }

    /**
     * @return Generator<string>
     */
    private function daysBetween(string $start, string $end): Generator
    {
        $count = count(explode('-', $start));

        if ($count === 2) {
            // Use an arbitrary leap year
            $start = '2024-'.$start;
            $end = '2024-'.$end;
        }

        $startDate = new DateTimeImmutable($start);
        $endDate = $startDate->modify($end)->modify('+12 hours');

        $format = [
            2 => 'm-d',
            3 => 'Y-m-d',
        ][$count] ?? 'l';

        foreach (new DatePeriod($startDate, new DateInterval('P1D'), $endDate) as $date) {
            yield $date->format($format);
        }
    }

    private function getOpeningHoursFromStrings(array $openingHours): OpeningHoursForDay
    {
        $data = $openingHours['data'] ?? null;
        unset($openingHours['data']);

        return OpeningHoursForDay::fromStrings($openingHours, $data);
    }

    private function setExceptionsFromStrings(array $exceptions): void
    {
        if ($exceptions === []) {
            return;
        }

        if (!$this->dayLimit) {
            $this->dayLimit = 366;
        }

        $this->exceptions = Arr::map($exceptions, static function (array $openingHours, string $date): OpeningHoursForDay {
            $recurring = DateTime::createFromFormat('m-d', $date);

            if ($recurring === false || $recurring->format('m-d') !== $date) {
                $dateTime = DateTime::createFromFormat('Y-m-d', $date);

                if ($dateTime === false || $dateTime->format('Y-m-d') !== $date) {
                    throw InvalidDate::invalidDate($date);
                }
            }

            return OpeningHoursForDay::fromStrings($openingHours);
        });
    }

    private function normalizeDayName(Day|string $day): string
    {
        return (is_string($day) ? Day::fromName($day) : $day)->value;
    }

    private function applyTimezone(DateTimeInterface $date): DateTimeInterface
    {
        return $this->getDateWithTimezone($date, $this->timezone);
    }

    private function getDateWithTimezone(DateTimeInterface $date, ?DateTimeZone $timezone): DateTimeInterface
    {
        if ($timezone instanceof DateTimeZone) {
            if ($date instanceof DateTime) {
                $date = clone $date;
            }

            $date = $date->setTimezone($timezone);
        }

        return $date;
    }

    private function parseTimezone(mixed $timezone): ?DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        if (is_string($timezone)) {
            return new DateTimeZone($timezone);
        }

        if ($timezone) {
            throw InvalidTimezone::create();
        }

        return null;
    }

    private function getOutputTimezone(?DateTimeInterface $dateTime = null): ?DateTimeZone
    {
        if ($this->outputTimezone instanceof DateTimeZone) {
            return $this->outputTimezone;
        }

        if (!$this->timezone instanceof DateTimeZone || !$dateTime instanceof DateTimeInterface) {
            return $this->timezone;
        }

        return $dateTime->getTimezone();
    }
}
