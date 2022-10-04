<?php

namespace App\Models\SubModels;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Carbon              $usableFrom
 * @property Carbon              $usableUntil
 * @property array{test: string} $byMonthUsability
 */
class WPSemesterUsability extends Model {
  protected $fillable = [
    "usableFrom",
    "usableUntil",
    "byMonthUsability",
  ];
}
