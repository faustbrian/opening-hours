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
 * Base exception for malformed Schema.org opening-hours specifications.
 *
 * This separates structured-data import failures from invalid native array
 * definitions so callers can report third-party data problems with more
 * precise context.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidOpeningHoursSpecification extends InvalidArgumentException implements OpeningHoursException {}
