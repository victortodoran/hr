<?php

use App\Support\Currency;

covers(Currency::class);

it('knows how many decimals a currency has', function (string $code, int $decimals) {
    expect((new Currency($code))->decimals())->toBe($decimals);
})->with([
    'euro' => ['EUR', 2],
    'yen' => ['JPY', 0],
    'Bahraini dinar' => ['BHD', 3],
]);

it('turns an amount as people type it into minor units', function (string $code, string $amount, int $minorUnits) {
    expect((new Currency($code))->toMinorUnits($amount))->toBe($minorUnits);
})->with([
    'whole euros' => ['EUR', '4500', 450000],
    'euros and cents' => ['EUR', '4500.5', 450050],
    'both decimals' => ['EUR', '22.75', 2275],
    'cents only' => ['EUR', '.05', 5],
    'a trailing point' => ['EUR', '12.', 1200],
    'a plus sign' => ['EUR', '+3.10', 310],
    'leading zeros' => ['EUR', '007.00', 700],
    'yen' => ['JPY', '5000', 5000],
    'dinar' => ['BHD', '1.5', 1500],
]);

it('shows minor units as an amount in the currency', function (string $code, int $minorUnits, string $shown) {
    expect((new Currency($code))->format($minorUnits))->toBe($shown);
})->with([
    'euro' => ['EUR', 450050, '€4,500.50'],
    'yen' => ['JPY', 5000, '¥5,000'],
    'dinar' => ['BHD', 1500, "BHD\u{a0}1.500"],
]);
