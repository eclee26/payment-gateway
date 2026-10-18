<?php

namespace App\Enums;

/**
 * What handling a provider callback did to the payment it refers to.
 */
enum CallbackOutcome
{
    /** The payment was pending and now reflects the provider's outcome. */
    case Applied;

    /** A repeat of an outcome the payment already reflects. */
    case AlreadyApplied;

    /**
     * The provider reports an outcome that contradicts the payment's final
     * state, e.g. success for a payment that was canceled or expired. Money
     * may have moved, so it is logged for reconciliation.
     */
    case Conflict;

    /** No payment with this reference (yet) for the provider. */
    case NotFound;
}
