<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'CARD';
    case Qrph = 'QRPH';
    case Gcash = 'GCASH';
    case Maya = 'MAYA';
    case Ecpay = 'ECPAY';
    case Instapay = 'INSTAPAY';
    case Pesonet = 'PESONET';

    /**
     * Whether the customer completes the payment outside the API call
     * (QR scan, wallet redirect, bank transfer, over-the-counter).
     */
    public function isAsync(): bool
    {
        return $this !== self::Card;
    }

    /**
     * Currencies this payment method can be charged in.
     *
     * @return list<string>
     */
    public function currencies(): array
    {
        return $this === self::Card ? config('gateway.currencies') : ['PHP'];
    }

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Card',
            self::Qrph => 'QR Ph',
            self::Gcash => 'GCash',
            self::Maya => 'Maya',
            self::Ecpay => 'ECPay',
            self::Instapay => 'InstaPay',
            self::Pesonet => 'PESONet',
        };
    }
}
