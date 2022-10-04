<?php

namespace App\Traits;

use Illuminate\Validation\Validator;

trait ValidatesRouteParameters {
  private function validateRouteParams($data, $rules): array {
    /**
     * @var Validator $validator
     */
    $validator = validator($data, $rules);
    
    return $validator->validated();
  }
}
