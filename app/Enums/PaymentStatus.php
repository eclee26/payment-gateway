<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case RequiresAction = 'requires_action';
    case RequiresCapture = 'requires_capture';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /**
     * Whether the payment has captured funds that can still be refunded.
     */
    public function isRefundable(): bool
    {
        return in_array($this, [self::Succeeded, self::PartiallyRefunded], true);
    }
}
