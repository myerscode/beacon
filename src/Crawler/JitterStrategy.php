<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Crawler;

/**
 * Jitter strategy for introducing randomness into delay intervals.
 *
 * NONE: No jitter applied, delays are deterministic.
 * FULL: Delay is randomized between 0 and the calculated interval.
 *
 * @see https://docs.aws.amazon.com/step-functions/latest/dg/concepts-error-handling.html
 */
enum JitterStrategy: string
{
    case NONE = 'none';
    case FULL = 'full';
}
