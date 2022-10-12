<?php

namespace App\Models;

use App\Enums\ClubPackType;
use App\Enums\UserRole;
use App\Enums\WPMovementType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Jenssegers\Mongodb\Auth\User as Authenticatable;
use Jenssegers\Mongodb\Eloquent\HybridRelations;
use Jenssegers\Mongodb\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\BSON\ObjectId;

/**
 * @mixin \Illuminate\Database\Query\Builder
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property ObjectId             $_id
 * @property string               $firstName
 * @property string               $lastName
 * @property string               $clubPack
 * @property string               $clubCardNumber
 * @property Collection<Movement> $walletPremiumMovements
 * @property Collection<UserRole> roles
 *
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
    'firstName',
    'lastName',
    'email',
    'roles',
    'apps',
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
  
  public function getRolesAttribute($value = null): Collection {
    return collect($value);
  }
  
  /**
   * @return HasMany
   */
  public function walletPremiumMovements(): HasMany {
    return $this->hasMany(WPMovement::class, 'userId', '_id');
  }
  
  
  /**
   * @return HasMany
   */
  public function walletPremiumMustWithdrawCurrMonth(): HasMany {
    return $this->hasMany(WPMovement::class, 'userId', '_id')
      ->where('movementType', WPMovementType::MONTHLY_INCOME)
      ->where('withdrawableFrom', '<=', Carbon::now())
      ->where('withdrawableUntil', '>=', Carbon::now())
      ->where('withdrawalRemaining', '>', 0);
  }
  
  public function getFullName(): string {
    return $this->firstName . " " . $this->lastName;
  }
  
  public function hasRole($role): bool {
    return $this->roles->contains($role);
  }
  
  public function hasAnyRole($roles): bool {
    return $this->roles->intersect($roles)->isNotEmpty();
  }
  
  public function isAdmin(): bool {
    return $this->hasAnyRole([UserRole::ADMIN, UserRole::SUPER_ADMIN, UserRole::CLUB_ADMIN]);
  }
}
