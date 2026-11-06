<?php

namespace App\Services\Subscription;

use RuntimeException;

/**
 * A subscription action the owner cannot take right now; the message is shown to them.
 */
class SubscriptionException extends RuntimeException
{
}
