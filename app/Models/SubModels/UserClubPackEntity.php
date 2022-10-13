<?php

namespace App\Models\SubModels;

use App\Enums\ClubPackType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;
use Jenssegers\Mongodb\Query\Builder;

/**
 * @mixin Builder
 *
 * @property ClubPackType $pack
 * @property string       $startsAt
 * @property string       $endsAt
 * @property float        $cost
 * @property string       $orderId
 * @property string       $createdAt
 * @property string       $updatedAt
 */
class UserClubPackEntity extends Model {
  protected $keyType = "string";
  
  protected $fillable = [
    "pack",
    "startsAt",
    "endsAt",
    "cost",
    "orderId",
  ];
  
  protected $dates = [
    "startsAt",
    "endsAt",
    "createdAt",
    "updatedAt",
  ];
  
  protected $casts = [
    "startsAt"  => "datetime",
    "endsAt"    => "datetime",
    "createdAt" => "datetime",
    "updatedAt" => "datetime",
  ];
  
  public function __construct(array $attributes = []) {
    parent::__construct($attributes);
    
    $this->createdAt = Carbon::now();
    $this->updatedAt = Carbon::now();
  }
}
