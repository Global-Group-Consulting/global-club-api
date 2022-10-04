<?php

namespace App\Http\Requests;

use App\Rules\SemesterId;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest {
  
  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize() {
    //TODO:: check user type and if is withdrawing for it self
    return true;
  }
  
  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, mixed>
   */
  public function rules() {
    return [
      "amount"      => "required|numeric",
//      "semesterId"  => ["required", new SemesterId],
      "userCardNum" => "nullable|string",
    ];
  }
}
