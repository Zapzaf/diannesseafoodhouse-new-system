<?php

namespace App\Support;

class VatCalculator
{
    private const VAT_RATE = 0.12;

    /**
     * Split a VAT-inclusive amount into its net and VAT components.
     *
     * @return array{net_purchases: float, vat: float}
     */
    public static function split(float $amountWithVat): array
    {
        $net = round($amountWithVat / (1 + self::VAT_RATE), 2);

        return [
            'net_purchases' => $net,
            'vat' => round($amountWithVat - $net, 2),
        ];
    }
}
