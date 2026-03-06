<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use Cline\OpeningHours\Exceptions\InvalidOpeningHoursSpecification;
use JsonException;

use const JSON_THROW_ON_ERROR;

use function array_merge;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_replace;
use function sprintf;
use function throw_if;
use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class OpeningHoursSpecificationParser
{
    private array $openingHours = [];

    private function __construct(array $openingHoursSpecification)
    {
        foreach ($openingHoursSpecification as $index => $openingHoursSpecificationItem) {
            try {
                $this->parseOpeningHoursSpecificationItem($openingHoursSpecificationItem);
            } catch (InvalidOpeningHoursSpecification $exception) {
                $message = $exception->getMessage();

                throw new InvalidOpeningHoursSpecification(sprintf('Invalid openingHoursSpecification item at index %s: %s', $index, $message), $exception->getCode(), previous: $exception);
            }
        }
    }

    public static function createFromArray(array $openingHoursSpecification): self
    {
        return new self($openingHoursSpecification);
    }

    public static function createFromString(string $openingHoursSpecification): self
    {
        try {
            return self::createFromArray(json_decode(
                $openingHoursSpecification,
                true,
                flags: JSON_THROW_ON_ERROR,
            ));
        } catch (JsonException $jsonException) {
            throw new InvalidOpeningHoursSpecification('Invalid https://schema.org/OpeningHoursSpecification JSON', $jsonException->getCode(), previous: $jsonException);
        }
    }

    public static function create(array|string $openingHoursSpecification): self
    {
        return is_string($openingHoursSpecification)
            ? self::createFromString($openingHoursSpecification)
            : self::createFromArray($openingHoursSpecification);
    }

    public function getOpeningHours(): array
    {
        return $this->openingHours;
    }

    /**
     * Regular opening hours.
     */
    private function addDaysOfWeek(
        array $dayOfWeek,
        mixed $opens,
        mixed $closes,
    ): void {
        // Multiple days of week for same specification
        foreach ($dayOfWeek as $dayOfWeekItem) {
            throw_unless(is_string($dayOfWeekItem), InvalidOpeningHoursSpecification::class, 'Invalid https://schema.org/OpeningHoursSpecification dayOfWeek');

            $this->addDayOfWeekHours($dayOfWeekItem, $opens, $closes);
        }
    }

    private function schemaOrgDayToString(string $schemaOrgDaySpec): string
    {
        // Support official and Google-flavored Day specifications
        return match ($schemaOrgDaySpec) {
            'Monday', 'https://schema.org/Monday' => 'monday',
            'Tuesday', 'https://schema.org/Tuesday' => 'tuesday',
            'Wednesday', 'https://schema.org/Wednesday' => 'wednesday',
            'Thursday', 'https://schema.org/Thursday' => 'thursday',
            'Friday', 'https://schema.org/Friday' => 'friday',
            'Saturday', 'https://schema.org/Saturday' => 'saturday',
            'Sunday', 'https://schema.org/Sunday' => 'sunday',
            'PublicHolidays', 'https://schema.org/PublicHolidays' => throw new InvalidOpeningHoursSpecification(
                'PublicHolidays not supported',
            ),
            default => throw new InvalidOpeningHoursSpecification(
                'Invalid https://schema.org Day specification',
            ),
        };
    }

    private function addDayOfWeekHours(
        string $dayOfWeek,
        mixed $opens,
        mixed $closes,
    ): void {
        $dayOfWeek = self::schemaOrgDayToString($dayOfWeek);

        $hours = $this->formatHours($opens, $closes);

        if ($hours === null) {
            return;
        }

        $this->openingHours[$dayOfWeek][] = $hours;
    }

    private function addExceptionsHours(
        string $validFrom,
        string $validThrough,
        mixed $opens,
        mixed $closes,
    ): void {
        throw_unless(preg_match('/^(?:\d{4}-)?\d{2}-\d{2}$/', $validFrom), InvalidOpeningHoursSpecification::class, 'Invalid validFrom date');

        throw_unless(preg_match('/^(?:\d{4}-)?\d{2}-\d{2}$/', $validThrough), InvalidOpeningHoursSpecification::class, 'Invalid validThrough date');

        $exceptionKey = $validFrom === $validThrough ? $validFrom : $validFrom.' to '.$validThrough;

        $this->openingHours['exceptions'] ??= [];
        // Default to close all day
        $this->openingHours['exceptions'][$exceptionKey] ??= [];

        $hours = $this->formatHours($opens, $closes);

        if ($hours === null) {
            return;
        }

        $this->openingHours['exceptions'][$exceptionKey][] = $hours;
    }

    private function formatHours(mixed $opens, mixed $closes): ?string
    {
        if ($opens === null) {
            throw_if($closes !== null, InvalidOpeningHoursSpecification::class, 'Property opens and closes must be both null or both string');

            return null;
        }

        throw_if(!is_string($opens) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $opens), InvalidOpeningHoursSpecification::class, 'Invalid opens hour');

        throw_if(!is_string($closes) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $closes), InvalidOpeningHoursSpecification::class, 'Invalid closes hours');

        // strip seconds part if present
        $opens = preg_replace('/^(\d{2}:\d{2})(:\d{2})?$/', '$1', $opens);
        $closes = preg_replace('/^(\d{2}:\d{2})(:\d{2})?$/', '$1', $closes);

        // Ignore 00:00-00:00 which means closed all day
        if ($opens === '00:00' && $closes === '00:00') {
            return null;
        }

        return $opens.'-'.($closes === '23:59' ? '24:00' : $closes);
    }

    private function parseOpeningHoursSpecificationItem(mixed $openingHoursSpecificationItem): void
    {
        // extract $openingHoursSpecificationItem keys into variables
        [
            'dayOfWeek' => $dayOfWeek,
            'validFrom' => $validFrom,
            'validThrough' => $validThrough,
            'opens' => $opens,
            'closes' => $closes,
        ] = array_merge([
            // Default values:
            'dayOfWeek' => null,
            'validFrom' => null,
            'validThrough' => null,
            'opens' => null,
            'closes' => null,
        ], $openingHoursSpecificationItem);

        if ($dayOfWeek !== null) {
            if (is_string($dayOfWeek)) {
                $dayOfWeek = [$dayOfWeek];
            }

            throw_unless(is_array($dayOfWeek), InvalidOpeningHoursSpecification::class, 'Property dayOfWeek must be a string or an array of strings');

            $this->addDaysOfWeek($dayOfWeek, $opens, $closes);

            return;
        }

        throw_if(!is_string($validFrom) || !is_string($validThrough), InvalidOpeningHoursSpecification::class, 'Contains neither dayOfWeek nor validFrom and validThrough dates');

        /*
         * Exception opening hours
         */
        $this->addExceptionsHours($validFrom, $validThrough, $opens, $closes);
    }
}
