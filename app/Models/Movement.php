<?php

namespace App\Models;

use App\Enums\ClubPackType;
use App\Enums\MovementType;
use App\Models\SubModels\PremiumBySemesterEntry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
 */
class Movement extends Model {
  use HasFactory;
  
  protected $primaryKey = '_id';
  
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
  public static function getPremiumBySemester(string $semesterId, bool $includeMovements = false): Collection {
    $res = self::raw(function ($collection) use ($semesterId) {
      return $collection->aggregate([
        ['$match' => ["clubPack" => ClubPackType::PREMIUM, "semesterId" => $semesterId]],
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
