<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

class ObjectId implements InvokableRule {
  /**
   * Run the validation rule.
   *
   * @param  string                                                                 $attribute
   * @param  mixed                                                                  $value
   * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
   *
   * @return void
   */
  public function __invoke($attribute, $value, $fail) {
    try {
      new \MongoDB\BSON\ObjectId($value);
    } catch (\Exception $e) {
      $fail('The :attribute must be a valid id.');
    }
  }
}
