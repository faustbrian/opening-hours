<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use InvalidArgumentException;

/**
 * Base exception for malformed package-native opening-hours definitions.
 *
 * Exceptions extending this type indicate that the caller supplied an invalid
 * array definition while using the package's own weekday-and-exceptions input
 * format, rather than the Schema.org import format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidOpeningHoursDefinition extends InvalidArgumentException implements OpeningHoursException {}
