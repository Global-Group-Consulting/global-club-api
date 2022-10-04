<?php

namespace App\Models;

use App\Casts\MongoObjectId;
use App\Classes\CustomModel;
use App\Enums\ClubPackType;
use App\Enums\MovementType;
use App\Models\SubModels\PremiumBySemesterEntry;
use App\Models\SubModels\Semester;
use App\Traits\Models\CamelCasing;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * @mixin Builder
 *
 * @property string $_id
 * @property string $userId
 * @property float  $amountChange
 * @property string $movementType
 * @property string $referenceSemester
 * @property string $usableFrom
 * @property string $expiresAt
 * @property string $createdAt
 * @property string $updatedAt
 * @property string $semesterId
 * @property string $clubPack
 * @property string $fromUUID
 * @property string $createdBy
 * @property string $notes
 * @property string $order
 * @property string $clubPackChange
 * @property string $referenceYear
 */
class Movement extends CustomModel {
  use HasFactory;
  
  protected $primaryKey = '_id';
  
  protected $fillable = [
    'userId',
    'amountChange',
    'movementType',
    'referenceSemester',
    'semesterId',
    'clubPack',
    'fromUUID',
    'amountChange',
    'notes',
    'order',
    'referenceYear',
  ];
  
  protected $dates = ['usableFrom', 'expiresAt'];
  
  protected $casts = [
    "userId"     => MongoObjectId::class,
    "usableFrom" => "datetime",
    "expiresAt"  => "datetime",
  ];
  
  public static function boot() {
    parent::boot();
    
    self::creating(function (Movement $movement) {
      $parsedSemester       = Semester::parse($movement->semesterId);
      $movement->expiresAt  = $parsedSemester->usableUntil;
      $movement->usableFrom = $parsedSemester->usableFrom;
      
      $movement->referenceSemester = $parsedSemester->semester;
      $movement->referenceYear     = $parsedSemester->year;
    });
  }
  
  public function user() {
    return $this->belongsTo(User::class, 'userId', '_id');
  }
  
  
  /**
   * Return a list of totals grouped by users for the specified semester.
   * The semester should be the one that has just expired
   *
   * This won't check if the user currently is premium or not.
   *
   * @param  string  $semesterId
   * @param  bool    $includeMovements
   *
   * @return Collection<PremiumBySemesterEntry>
   */
  public static function getPremiumBySemester(string $semesterId, array $userIds = null, bool $includeMovements = false): Collection {
    $res = self::raw(function ($collection) use ($semesterId, $userIds) {
      $match = ["clubPack" => ClubPackType::PREMIUM, "semesterId" => $semesterId];
      
      if ($userIds) {
        $userIds = collect($userIds)->map(function ($userId) {
          return new \MongoDB\BSON\ObjectId($userId);
        })->toArray();
        
        $match["userId"] = ['$in' => $userIds];
      }
      
      return $collection->aggregate([
        ['$match' => $match],
        ['$group' => [
          "_id"          => '$userId',
          // "movements"    => ['$push' => '$$ROOT'],
          "inMovements"  => ['$push' => ['$cond' => [['$in' => ['$movementType', MovementType::IN_MOVEMENTS]], '$$ROOT', null]]],
          "outMovements" => ['$push' => ['$cond' => [['$in' => ['$movementType', MovementType::OUT_MOVEMENTS]], '$$ROOT', null]]],
        ]],
        ['$addFields' => [
          "inAmount"  => ['$sum' => '$inMovements.amountChange'],
          "outAmount" => ['$sum' => '$outMovements.amountChange'],
          "semester"  => $semesterId,
        ]],
        ['$addFields' => [
          "remainingAmount" => ['$round' => [['$subtract' => ['$inAmount', '$outAmount']], 0]],
          "userId"          => '$_id',
        ]],
        ['$match' => ["remainingAmount" => ['$gt' => 0]]],
      ]);
    });
    
    // ensure that $res is an instance of Collection
    if ( !($res instanceof Collection)) {
      $res = collect($res);
    }
    
    return $res->map(function ($item) use ($includeMovements) {
      // if is requested to NOT include movements, then remove them from the result
      if ( !$includeMovements) {
        Arr::forget($item, "inMovements");
        Arr::forget($item, "outMovements");
      }
      
      return new PremiumBySemesterEntry($item->toArray());
    });
  }
  
}
