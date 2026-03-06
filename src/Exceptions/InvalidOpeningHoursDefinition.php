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
 * Base exception for invalid package-native opening-hours definitions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidOpeningHoursDefinition extends InvalidArgumentException implements OpeningHoursException {}
