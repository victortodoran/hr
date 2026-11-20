<?php

namespace App\Support;

use Illuminate\Support\Number;
use NumberFormatter;

/** Amounts are whole minor units, such as cents, so they never lose precision (ADR 0003). */
final readonly class Currency
{
    public function __construct(public string $code) {}

    public function decimals(): int
    {
        return (int) (new NumberFormatter("en@currency={$this->code}", NumberFormatter::CURRENCY)) // @pest-mutate-ignore (the int return type converts it anyway)
            ->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    /** The amount must already be validated as positive, within the currency's decimals and below a billion, so rounding is always exact. */
    public function toMinorUnits(string $amount): int
    {
        return (int) round((float) $amount * 10 ** $this->decimals()); // @pest-mutate-ignore (the int return type converts it anyway)
    }

    public function toAmount(int $minorUnits): string
    {
        $decimals = $this->decimals();

        return number_format($minorUnits / 10 ** $decimals, $decimals, '.', '');
    }

    public function format(int $minorUnits): string
    {
        $decimals = $this->decimals();

        $shown = Number::currency($minorUnits / 10 ** $decimals, $this->code, 'en', $decimals);

        return (string) $shown; // @pest-mutate-ignore (it is only false without the intl extension, which the app needs)
    }
}
