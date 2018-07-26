<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Пример валидации на простую дробь
 */

class SimpleFraction implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {

        $passes = true;
        if($value == 1) return $passes;
        if ($value) {
            $passes = is_string($value) && preg_match('/^[1-9]\d*[\/]{1}[1-9]\d*$/u', $value);
        }
        if($passes){
            $frs = explode('/', $value);
            $numerator = (int)$frs[0];
            $denominator = (int)$frs[1];
            if ($numerator > $denominator) $passes = false;
        }
        return $passes;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "Поле должно быть простой дробью или 1.";
    }
}
