[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# opening-hours

An immutable opening-hours engine built around typed schedules,
date-specific override rules, and schema.org adapters.

## Requirements

> **Requires [PHP 8.5+](https://php.net/releases/)**

## Installation

```bash
composer require cline/opening-hours
```

## Usage

```php
use Cline\OpeningHours\OpeningHours;
use Cline\OpeningHours\QueryOptions;

$openingHours = OpeningHours::fromArray([
    'monday' => ['09:00-17:00'],
    'tuesday' => ['09:00-17:00'],
    'exceptions' => [
        '2026-12-24' => [],
        '2026-12-31 to 2027-01-02' => ['10:00-14:00'],
        '01-01' => [],
    ],
], new QueryOptions(
    timezone: 'Europe/Helsinki',
));

$openingHours->isOpenAt(new DateTimeImmutable('2026-03-09 10:00:00'));
$openingHours->nextOpen(new DateTimeImmutable('2026-03-09 18:00:00'));
$openingHours->asStructuredData();
```

## V2 API

- `OpeningHours::fromArray()` builds schedules from weekday definitions
  plus explicit exception rules.
- `OpeningHours::fromWeeklySchedule()` builds directly from typed value
  objects.
- `OpeningHours::createFromStructuredData()` imports schema.org opening
  hour specifications.
- `QueryOptions` controls input timezone, output timezone, and search
  limits for transition queries.

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/opening-hours/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/opening-hours.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/opening-hours.svg

[link-tests]: https://github.com/faustbrian/opening-hours/actions
[link-packagist]: https://packagist.org/packages/cline/opening-hours
[link-downloads]: https://packagist.org/packages/cline/opening-hours
[link-security]: https://github.com/faustbrian/opening-hours/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
