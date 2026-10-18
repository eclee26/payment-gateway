<?php

namespace Tests\Unit;

use App\Models\ProviderPaymentMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProviderPaymentMethodFeeTest extends TestCase
{
    /**
     * @return array<string, array{int, int, int, int}>
     */
    public static function fees(): array
    {
        return [
            'percentage only' => [10_000, 250, 0, 250],
            'fixed only' => [10_000, 0, 1500, 1500],
            'both' => [2500, 290, 1500, 1573],
            'rounds half up' => [50, 100, 0, 1],
            'rounds down below half' => [49, 100, 0, 0],
            'free' => [10_000, 0, 0, 0],
        ];
    }

    #[DataProvider('fees')]
    public function test_fee_calculation(int $amount, int $bps, int $fixed, int $expected): void
    {
        $pricing = new ProviderPaymentMethod;
        $pricing->fee_bps = $bps;
        $pricing->fee_fixed = $fixed;

        $this->assertSame($expected, $pricing->feeFor($amount));
    }
}
