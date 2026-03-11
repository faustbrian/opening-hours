<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use Throwable;

/**
 * Marker interface for all package-specific failures.
 *
 * Consumers can catch this interface to handle validation, parsing, and query
 * errors raised by the opening-hours package without also swallowing unrelated
 * framework or PHP exceptions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface OpeningHoursException extends Throwable {}
