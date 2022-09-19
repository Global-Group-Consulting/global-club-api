<?php

namespace App\Http\Requests;

use App\Rules\SemesterId;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawBySemesterRequest extends FormRequest {
  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize() {
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
      "semesters"   => ["required", "array", new SemesterId()],
      "userCardNum" => "nullable|string",
    ];
  }
}
