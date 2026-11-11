<?php

namespace App\Support;

use Illuminate\Support\Number;
use NumberFormatter;

/**
 * The company currency (ADR 0003), which every Pay is recorded in. Amounts
 * are stored as whole minor units, such as cents, so they never lose
 * precision; how many decimals that is depends on the currency.
 */
final readonly class Currency
{
    /**
     * @param  string  $code  A three-letter code, such as EUR
     */
    public function __construct(public string $code) {}

    /**
     * How many decimals an amount has: 2 for the euro, 0 for the yen.
     */
    public function decimals(): int
    {
        return (int) (new NumberFormatter("en@currency={$this->code}", NumberFormatter::CURRENCY)) // @pest-mutate-ignore (the int return type converts it anyway)
            ->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }

    /**
     * An amount as people type it, such as "4500.50", in minor units. The
     * amount must already be a positive number with no more decimals than
     * the currency has, and below a billion, so rounding is always exact.
     */
    public function toMinorUnits(string $amount): int
    {
        return (int) round((float) $amount * 10 ** $this->decimals()); // @pest-mutate-ignore (the int return type converts it anyway)
    }

    /**
     * Minor units as people read them, such as "€4,500.50".
     */
    public function format(int $minorUnits): string
    {
        $decimals = $this->decimals();

        $shown = Number::currency($minorUnits / 10 ** $decimals, $this->code, 'en', $decimals);

        return (string) $shown; // @pest-mutate-ignore (it is only false without the intl extension, which the app needs)
    }
}
