<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use ResourceBundle;

/**
 * The codes come from ICU's data in the intl extension, so withdrawn
 * currencies such as DEM are refused too (ADR 0003).
 */
class CurrencyInUse implements ValidationRule
{
    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array($value, self::codes(), true)) {
            $fail(__('The currency must be the three-letter code of a currency in use, such as EUR.'));
        }
    }

    /** @return list<string> */
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
