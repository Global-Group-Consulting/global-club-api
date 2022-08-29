<?php

namespace App\Models;

use App\Enums\ClubPackType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Jenssegers\Mongodb\Auth\User as Authenticatable;
use Jenssegers\Mongodb\Eloquent\HybridRelations;
use Jenssegers\Mongodb\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * @mixin Builder
 *
 * @property string               _id
 * @property string               clubPack
 * @property Collection<Movement> $walletPremiumMovements
 */
class User extends Authenticatable {
  use HasApiTokens, HasFactory, HybridRelations;
  
  protected $connection = "mongodb_legacy";
  
  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'name',
    'email',
    'password',
  ];
  
  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];
  
  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'email_verified_at' => 'datetime',
  ];
  
  /**
   * @return bool
   */
  public function isPremium(): bool {
    return $this->clubPack === ClubPackType::PREMIUM;
  }
  
  public function getIdAttribute($value = null) {
    return $value;
  }
  
  /**
   * @return HasMany
   */
  public function walletPremiumMovements(): HasMany {
    return $this->hasMany(WPMovement::class, 'userId', '_id');
  }
}
