<?php

namespace App\Models;

use App\Casts\MongoObjectId;
use App\Classes\CustomModel;
use App\Enums\HttpStatusCodes;
use App\Enums\MovementType;
use App\Enums\WPMovementType;
use App\Exceptions\WpMovementHttpException;
use App\Models\SubModels\Semester;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Relations\BelongsTo;

/**
 * @mixin Builder
 * @mixin \Illuminate\Database\Query\Builder
 *
 * @property string     $_id
 * @property string     $userId
 * @property float      $initialAmount
 * @property float      $incomeAmount
 * @property int        $incomePercentage
 * @property string     $semester
 * @property int        $referenceSemester
 * @property int        $referenceYear
 * @property int        $referenceUsableUntil - Date until the amount was usable
 * @property Carbon     $withdrawalDate       - Date when all the amount was withdrawn
 * @property float      $withdrawalRemaining  - Amount remaining after each withdrawal. Initially is the same as the $incomeAmount
 * @property array      $withdrawalMovements  - Array of movements that were used to withdraw the amount
 * @property Carbon     $withdrawableFrom
 * @property Carbon     $withdrawableUntil
 * @property string     $movementType
 *
 * @property-read  bool $hasWithdrawMovements
 */
class WPMovement extends CustomModel {
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
   * The accessors to append to the model's array form.
   *
   * @var array
   */
  protected $appends = ['hasWithdrawMovements'];
  
  
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
      
      if ($model->movementType === WPMovementType::MONTHLY_INCOME) {
        $model->withdrawalRemaining = $model->incomeAmount;
        $model->withdrawalMovements = [];
      }
      
      if ($model->withdrawableFrom) {
        $model->withdrawalDate = null;
      }
      
      return $model;
    });
  }
  
  /**
   * @param  Movement  $movement
   *
   * @return void
   */
  public function addWithdrawMovement(Movement $movement): void {
    $movementData = $movement->toArray();
    
    if ($movement->movementType === MovementType::DEPOSIT_RECEIVED_WP) {
      $user = $movement->user;
      
      $movementData["notes"] = "Wallet Premium - Trasferimento a favore di {$user->getFullName()}" . ($user->clubCardNumber ? " ($user->clubCardNumber)" : '');
    }
    
    if (is_null($this->withdrawalMovements)) {
      $this->withdrawalMovements = [$movementData];
    } else {
      $this->withdrawalMovements = array_merge([$movementData], $this->withdrawalMovements);
    }
    
    $this->withdrawalRemaining -= $movement->amountChange;
    
    if ($this->withdrawalRemaining <= 0) {
      $this->withdrawalDate = Carbon::now();
    }
    
    $this->save();
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
   * Get the user's first name.
   *
   * @return bool
   */
  protected function getHasWithdrawMovementsAttribute(): bool {
    return $this->withdrawalMovements && count($this->withdrawalMovements) > 0;
  }
  
  /**
   * @return BelongsTo
   */
  public function user(): BelongsTo {
    return $this->belongsTo(User::class, 'userId', '_id');
  }
  
  /**
   * @param $amount
   *
   * @return void
   * @throws WpMovementHttpException
   */
  public function checkIsWithdrawable($amount): void {
    $now = Carbon::now();
    
    // check if the movement is withdrawable based on the dates
    // $now must be between the withdrawableFrom and withdrawableUntil dates
    if ( !$now->betweenIncluded($this->withdrawableFrom, $this->withdrawableUntil)) {
      throw new WpMovementHttpException(HttpStatusCodes::HTTP_NOT_ACCEPTABLE, "The movement is not withdrawable");
    }
    
    // check if the amount is not greater than the withdrawable amount
    if ($amount > $this->incomeAmount || $this->withdrawalRemaining < $amount) {
      throw new WpMovementHttpException(HttpStatusCodes::HTTP_NOT_ACCEPTABLE, "The amount is greater than the withdrawable amount");
    }
    
  }
  
  /**
   * @param  string  $semester
   * @param  User    $user
   * @param  bool    $includeMovements
   *
   * @return array{initialAmount: float, earned: float, withdrawal: float}
   */
  public static function getSemesterSummary(string $semester, User $user, bool $includeMovements = true): array {
    /**
     * @var Collection<WPMovement> $wpMovements
     */
    $wpMovements = $user->walletPremiumMovements()->where("semester", $semester)->get();
    $wpMovements->makeHidden(['withdrawalMovements']);
    $currMonthMovement = $wpMovements->firstWhere(fn($movement) => Carbon::now()->between($movement->withdrawableFrom, $movement->withdrawableUntil));
    
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
    /*$withdrawnAmount = $wpMovements->reduce(function ($acc, WPMovement $wpMovement) {
      if ( !$wpMovement->hasWithdrawMovements) {
        return $acc;
      }
      
      $withdrawn = $wpMovement->incomeAmount - $wpMovement->withdrawalRemaining;
      
      return $acc + $withdrawn;
    }, 0);*/
    $withdrawnAmount = $currMonthMovement->incomeAmount - $currMonthMovement->withdrawalRemaining;
    
    /**
     * Amount that is still available to be withdrawn this month
     */
    $withdrawableAmount = $currMonthMovement->withdrawalRemaining ?? 0;
    
    /**
     * Amount of the movements that has passed the withdrawableUntil date and has not been withdrawn yet.
     */
    $noMoreWithdrawableAmount = $wpMovements->reduce(function ($acc, WPMovement $wpMovement) {
      // ignore already withdrawn movements or movements that are not yet withdrawable
      if ( !$wpMovement->withdrawableFrom || $wpMovement->withdrawableFrom->greaterThan(Carbon::now())) {
        return $acc;
      }
      
      return $acc + $wpMovement->withdrawalRemaining;
    }, 0);
    
    /**
     * Amount that is still available to be withdrawn in the next months
     */
    $remainingToWithdraw = $wpMovements
      // Filter only the movements that are still withdrawable
      ->filter(fn($movement) => $movement->withdrawableUntil && $movement->withdrawableUntil->greaterThan(Carbon::now()))
      // Reduce and sum the remaining amount
      ->reduce(function ($acc, WPMovement $wpMovement) {
        return $acc + $wpMovement->withdrawalRemaining;
      }, 0);
    
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
  
  public static function getUserSummary(User $user, bool $includeMovements = true) {
    /**
     * @var Collection<WPMovement> $wpMovements
     */
    $wpMovements = $user->walletPremiumMovements()
      ->get();
    
    return $wpMovements->groupBy("semester");
  }
  
}
