<?php

namespace App\Helpers;

use Carbon\Carbon;

class Semester {
  /**
   * @param  string  $semester
   *
   * @return array{ year:int, semester:int, usableFrom:Carbon, usableUntil:Carbon, referenceSemester:int, referenceYear:int }
   */
  public static function parse(string $semester): array {
    $parsedSemester = explode("_", $semester);
    $month          = $parsedSemester[1] === "1" ? 1 : 7;
    $usableFrom     = Carbon::createFromFormat("Y_n", $parsedSemester[0] . "_" . $month)->addMonths(6)->startOfMonth();
    $usableUntil    = $usableFrom->copy()->addYear()->subtract(1, "millisecond");
    
    return [
      "year"              => (int) $parsedSemester[0],
      "referenceYear"     => (int) $parsedSemester[0],
      "semester"          => (int) $parsedSemester[1],
      "referenceSemester" => (int) $parsedSemester[1],
      "usableFrom"        => $usableFrom,
      "usableUntil"       => $usableUntil,
    ];
  }
}
