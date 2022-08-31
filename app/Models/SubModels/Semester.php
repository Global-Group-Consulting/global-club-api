<?php

namespace App\Models\SubModels;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string              $id
 * @property int                 $year
 * @property int                 $semester
 * @property Carbon              $usableFrom
 * @property Carbon              $usableUntil
 * @property WPSemesterUsability $walletPremium
 */
class Semester extends Model {
  protected $keyType = "string";
  
  protected $fillable = [
    "id",
    "year",
    "semester",
    "usableFrom",
    "usableUntil",
    "walletPremium",
  ];
  
  /**
   * Parse an incoming semester string and return the corresponding semester object.
   *
   * @param  string  $semester
   *
   * @return Semester
   */
  public static function parse(string $semester): Semester {
    $parsedSemester = explode("_", $semester);
    
    return new Semester([
      "id"            => $semester,
      "year"          => (int) $parsedSemester[0],
      "semester"      => (int) $parsedSemester[1],
      ...self::calcUsability($semester),
      "walletPremium" => self::calcWalletPremiumUsability($semester),
    ]);
  }
  
  /**
   * Calculate the usability for the given semester.
   *
   * @param  string  $semester
   *
   * @return array{usableFrom: Carbon, usableUntil: Carbon}
   */
  public static function calcUsability(string $semester): array {
    $parsedSemester = explode("_", $semester);
    $month          = $parsedSemester[1] === "1" ? 1 : 7;
    $usableFrom     = Carbon::createFromFormat("Y_n", $parsedSemester[0] . "_" . $month)->addMonths(6)->startOfMonth();
    $usableUntil    = $usableFrom->copy()->addYear()->subtract(1, "millisecond");
    
    return [
      "usableFrom"  => $usableFrom,
      "usableUntil" => $usableUntil,
    ];
  }
  
  /**
   * @param  string  $string
   *
   * @return WPSemesterUsability
   */
  public static function calcWalletPremiumUsability(string $string): WPSemesterUsability {
    $usability = self::calcUsability($string);
    
    $usableFrom = $usability["usableUntil"]->copy()
      ->add(1, "millisecond")
      ->setDay(16)
      ->startOfDay();
    
    // usable for the next 24 months
    $usableUntil = $usableFrom->copy()->addMonth(24)
      ->setDay(15)
      ->endOfDay();
    
    $monthsUsability = [];
    
    // Create 24 future months usability
    for ($i = 0; $i < 24; $i++) {
      $monthUsability = [];
      
      $monthUsability["usableFrom"] = $usability["usableUntil"]->copy()
        ->add(1, "millisecond")
        ->addMonths($i)
        ->setDay(16)
        ->startOfDay();
      
      // usable until the next month
      $monthUsability["usableUntil"] = $monthUsability["usableFrom"]->copy()
        ->addMonth()
        ->setDay(15)
        ->endOfDay();
      
      $monthsUsability[] = $monthUsability;
    }
    
    return new WPSemesterUsability([
      "usableFrom"       => $usableFrom,
      "usableUntil"      => $usableUntil,
      "byMonthUsability" => $monthsUsability
    ]);
  }
  
  /**
   * Returns an array of all the semesters that are currently available.
   *
   * @param  int  $num
   *
   * @return array{id: string, details: Semester}
   */
  public static function getPastValidSemesters(int $pastYears = 4): array {
    $now       = Carbon::now();
    $lastYear  = $now->copy()->subYears($pastYears)->year;
    $semesters = [];
    
    for ($year = $lastYear; $year <= $now->year; $year++) {
      for ($semester = 1; $semester <= 2; $semester++) {
        $semesterId   = $year . "_" . $semester;
        $semesterData = self::parse($semesterId);
        
        // return only the semesters that have wallet premium usability
        if ($semesterData->walletPremium->usableUntil->isFuture()) {
          $semesters[] = [
            "id"      => $semesterId,
            "details" => $semesterData
          ];
        }
      }
    }
    
    return $semesters;
  }
}
