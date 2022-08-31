<?php

namespace App\Models;

use App\Casts\MongoObjectId;
use App\Enums\WPMovementType;
use App\Models\SubModels\Semester;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Builder;
use Jenssegers\Mongodb\Eloquent\Model;
use Jenssegers\Mongodb\Relations\BelongsTo;

/**
 * @mixin Builder
 *
 * @property string $_id
 * @property string $userId
 * @property float  $initialAmount
 * @property float  $incomeAmount
 * @property int    $incomePercentage
 * @property string $semester
 * @property int    $referenceSemester
 * @property int    $referenceYear
 * @property int    $referenceUsableUntil - Date until the amount was usable
 * @property Carbon $withdrawalDate
 * @property Carbon $withdrawableFrom
 * @property Carbon $withdrawableUntil
 * @property string $movementType
 */
class WPMovement extends Model {
  use HasFactory;
  
  protected $table = "wp_movements";
  protected $connection = "mongodb";
  
  protected $fillable = [
    "userId",
    "initialAmount",
    "incomeAmount",
    "incomePercentage",
    "semester",
    "referenceSemester",
    "referenceYear",
    "referenceUsableUntil",
    "withdrawalDate",
    "withdrawableFrom",
    "withdrawableUntil",
    "movementType",
  ];
  
  protected $casts = [
    "userId"               => MongoObjectId::class,
    "initialAmount"        => "float",
    "incomeAmount"         => "float",
    "incomePercentage"     => "integer",
    "semester"             => "string",
    "referenceSemester"    => "integer",
    "referenceYear"        => "integer",
    "referenceUsableUntil" => "datetime",
    "withdrawalDate"       => "datetime",
    "withdrawableFrom"     => "datetime",
    "withdrawableUntil"    => "datetime",
    "movementType"         => "string",
  ];
  
  
  /**
   * The "booted" method of the model.
   *
   * @return void
   */
  protected static function booted(): void {
    /**
     * Before creating a new WPMovement, add some automatic fields
     */
    static::creating(function (WPMovement $model) {
      $parsedSemester = Semester::parse($model->semester);
      
      $model->referenceSemester    = $parsedSemester->semester;
      $model->referenceYear        = $parsedSemester->year;
      $model->referenceUsableUntil = $parsedSemester->usableUntil;
      
      if ($model->withdrawableFrom) {
        $model->withdrawalDate = null;
      }
      
      return $model;
    });
  }
  
  
  /**
   * Before setting the value of an attribute, check if it must be cast.
   *
   * @param  string  $key
   * @param  mixed   $value
   *
   * @return mixed
   */
  public function setAttribute($key, $value): mixed {
    if ( !key_exists($key, $this->casts)) {
      return parent::setAttribute($key, $value);
    }
    
    $castedValue = $this->castAttribute($key, $value);
    
    return parent::setAttribute($key, $castedValue);
  }
  
  /**
   * @return BelongsTo
   */
  public function user(): BelongsTo {
    return $this->belongsTo(User::class, 'userId', '_id');
  }
  
  /**
   * @param  string  $semester
   * @param  User    $user
   * @param  bool    $includeMovements
   *
   * @return array
   */
  public static function getSemesterSummary(string $semester, User $user, bool $includeMovements = true): array {
    $wpMovements = $user->walletPremiumMovements()->where("semester", $semester)->get();
    
    // If no movements were found, return an empty default array
    if ($wpMovements->count() === 0) {
      return [
        "initialAmount"       => 0,
        "earned"              => 0,
        "withdrawn"           => 0,
        "withdrawable"        => 0,
        "noMoreWithdrawable"  => 0,
        "remainingToWithdraw" => 0,
        "semesterDetails"     => Semester::parse($semester),
        "movements"           => $includeMovements ? [] : null,
      ];
    }
    
    /**
     * Amount that has already been withdrawn
     */
    $withdrawnAmount = $wpMovements->reduce(function ($acc, WPMovement $wpMovement) {
      if ( !$wpMovement->withdrawalDate) {
        return $acc;
      }
      
      return $acc + $wpMovement->incomeAmount;
    }, 0);
    
    /**
     * Amount that is still available to be withdrawn this month
     */
    $withdrawableAmount = $wpMovements->reduce(function ($acc, WPMovement $wpMovement) {
      // ignore already withdrawn movements
      if ($wpMovement->withdrawalDate || $wpMovement->movementType === WPMovementType::INITIAL_DEPOSIT) {
        return $acc;
      }
      
      if (Carbon::now()->betweenIncluded($wpMovement->withdrawableFrom, $wpMovement->withdrawableUntil)) {
        $acc = $acc + $wpMovement->incomeAmount;
      }
      
      return $acc;
    }, 0);
    
    /**
     * Amount of the movements that has passed the withdrawableUntil date and has not been withdrawn yet.
     */
    $noMoreWithdrawableAmount = $wpMovements->reduce(function ($acc, WPMovement $wpMovement) {
      // ignore already withdrawn movements or movements that are not yet withdrawable
      if ($wpMovement->withdrawalDate || ($wpMovement->withdrawableFrom && $wpMovement->withdrawableFrom->greaterThan(Carbon::now()))) {
        return $acc;
      }
      
      return $acc + $wpMovement->incomeAmount;
    }, 0);
    
    /**
     * Amount that is still available to be withdrawn in the next months
     */
    $remainingToWithdraw = $wpMovements->first()->initialAmount - $withdrawnAmount - $noMoreWithdrawableAmount;
    
    return [
      "initialAmount"       => $wpMovements->first()->initialAmount,
      "earned"              => $wpMovements->last()->incomeAmount,
      "withdrawn"           => $withdrawnAmount,
      "withdrawable"        => $withdrawableAmount,
      "noMoreWithdrawable"  => $noMoreWithdrawableAmount,
      "remainingToWithdraw" => $remainingToWithdraw,
      "semesterDetails"     => Semester::parse($semester),
      "movements"           => $includeMovements ? $wpMovements : null,
    ];
  }
  
}
