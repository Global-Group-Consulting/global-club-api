<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class SemesterId implements InvokableRule {
  /**
   * Run the validation rule.
   *
   * @param  string                                        $attribute
   * @param  mixed                                         $value
   * @param  Closure(string): PotentiallyTranslatedString  $fail
   *
   * @return void
   */
  public function __invoke($attribute, $value, $fail): void {
    if ( !is_array($value)) {
      $value = [$value];
    }
    
    foreach ($value as $semesterId) {
      if ( !Str::match("/^([0-9]{4})_(1|2)$/", $semesterId)) {
        $fail("The :attribute must be a valid semester id.");
      }
    }
    
    // if $match is an empty string, the semester is invalid
//    $match = Str::match("/^([0-9]{4})_(1|2)$/", $value);

//    if ( !$match) {
//      $fail('The :attribute must be a valid semester id.');
//    }
  }
}
