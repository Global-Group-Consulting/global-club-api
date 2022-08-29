<?php

namespace App\Models;

use App\Casts\MongoObjectId;
use App\Enums\WPMovementType;
use App\Helpers\Semester;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Database\Query\Builder;
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
 * @property string $withdrawalDate
 * @property string $withdrawableFrom
 * @property string $withdrawableUntil
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
  protected static function booted() {
    /**
     * Before creating a new WPMovement, add some automatic fields
     */
    static::creating(function (WPMovement $model) {
      $parsedSemester = Semester::parse($model->semester);
      
      $model->referenceSemester    = $parsedSemester["semester"];
      $model->referenceYear        = $parsedSemester["year"];
      $model->referenceUsableUntil = $parsedSemester["usableUntil"];
      
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
  
  public function getUserIdAttribute($value = null) {
    return $value;
  }
  
  /**
   * @return BelongsTo
   */
  public function user(): BelongsTo {
    return $this->belongsTo(User::class, 'userId', '_id');
  }
}
