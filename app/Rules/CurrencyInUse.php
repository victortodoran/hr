<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use ResourceBundle;

/**
 * A real ISO 4217 currency code, in capitals, that some country still uses
 * as legal tender, such as EUR: the company currency (ADR 0003). The codes
 * come from ICU's own data, which the intl extension already carries, so
 * withdrawn currencies such as DEM are refused along with made-up ones.
 */
class CurrencyInUse implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array($value, self::codes(), true)) {
            $fail(__('The currency must be the three-letter code of a currency in use, such as EUR.'));
        }
    }

    /**
     * The codes of every currency some region uses today: those in ICU's
     * currency map with no end date that are legal tender.
     *
     * @return list<string>
     */
    private static function codes(): array
    {
        $regions = ResourceBundle::create('supplementalData', 'ICUDATA-curr', false)?->get('CurrencyMap');
        $codes = [];

        foreach ($regions ?? [] as $currencies) {
            foreach ($currencies as $currency) {
                if ($currency['to'] === null && $currency['tender'] !== 'false') {
                    $codes[] = $currency['id'];
                }
            }
        }

        return $codes;
    }
}
