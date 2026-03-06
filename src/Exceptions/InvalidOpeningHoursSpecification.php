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
 * Base exception for invalid opening-hours specification input.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidOpeningHoursSpecification extends InvalidArgumentException implements OpeningHoursException {}
