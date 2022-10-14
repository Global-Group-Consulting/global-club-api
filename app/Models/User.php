<?php

namespace App\Models;

use App\Enums\AppType;
use App\Enums\ClubPackType;
use App\Enums\NotificationType;
use App\Enums\PlatformType;
use App\Enums\UserRole;
use App\Enums\WPMovementType;
use App\Jobs\CreateNotification;
use App\Models\SubModels\UserClubPackEntity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
 * @property UserClubPackEntity[] $clubPackHistory
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
  
  /**
   * Returns all users with expired Premium Pack
   *
   * @return \Illuminate\Database\Eloquent\Collection<User>|User[]|Collection<User>
   */
  public static function getExpiredPremiumUsers(): \Illuminate\Database\Eloquent\Collection|array|Collection {
    return User::where("clubPack", ClubPackType::PREMIUM)
      ->where("clubPackHistory.0.endsAt", "<", Carbon::now())
      ->get();
  }
  
  /**
   * Return a list of users that have a premium pack will expire soon
   *
   * @return array[]{1month: [], 1week: [], 1day: []}
   */
  public static function getExpiringPremiumUsers(): array {
    $toReturn = [
      "1month" => [],
      "1week"  => [],
      "1day"   => [],
    ];
    
    /**
     * @var []{1_month: Carbon, 1_week: Carbon, 1_day: Carbon} $expirations
     */
    $expirations = [
      "1_month_start" => Carbon::now()->addMonth()->startOfDay(),
      "1_month_end"   => Carbon::now()->addMonth()->endOfDay(),
      "1_week_start"  => Carbon::now()->addWeek()->startOfDay(),
      "1_week_end"    => Carbon::now()->addWeek()->endOfDay(),
      "1_day_start"   => Carbon::now()->addDay()->startOfDay(),
      "1_day_end"     => Carbon::now()->addDay()->endOfDay(),
    ];
    
    //    DB::connection("mongodb_legacy")->enableQueryLog();
    
    $users = User::where("clubPack", ClubPackType::PREMIUM)
      // I'm checking dates with between because not always the date is exactly the end of the day,
      // so in this way I can find even expirations that occur at any time in that day
      ->where(function ($query) use ($expirations) {
        $query->orWhereBetween("clubPackHistory.0.endsAt", [$expirations["1_month_start"], $expirations["1_month_end"]])
          ->orWhereBetween("clubPackHistory.0.endsAt", [$expirations["1_week_start"], $expirations["1_week_end"]])
          ->orwhereBetween("clubPackHistory.0.endsAt", [$expirations["1_day_start"], $expirations["1_day_end"]]);
      })->select("_id", "clubPackHistory")
      ->get();
    
    //    $query = DB::connection("mongodb_legacy")->getQueryLog();
    //    dd($query);
    
    // divide expiring users in 3 arrays based on the expiration date
    $toReturn["1month"] = self::searchBetweenClubPackHistory($users, $expirations["1_month_start"], $expirations["1_month_end"]);
    $toReturn["1week"]  = self::searchBetweenClubPackHistory($users, $expirations["1_week_start"], $expirations["1_week_end"]);
    $toReturn["1day"]   = self::searchBetweenClubPackHistory($users, $expirations["1_day_start"], $expirations["1_day_end"]);
    
    return $toReturn;
  }
  
  /**
   * Given a collection of users, search for the first pack that expires between the given dates
   *
   * @param  array|\Illuminate\Database\Eloquent\Collection  $array
   * @param  Carbon                                          $start
   * @param  Carbon                                          $end
   *
   * @return string[]
   */
  private static function searchBetweenClubPackHistory(array|Collection $array, Carbon $start, Carbon $end): array {
    $results = [];
    
    foreach ($array as $user) {
      /**
       * @var MongoDB\BSON\UTCDateTime $endsAt
       */
      $endsAt = $user["clubPackHistory"][0]["endsAt"]->toDateTime();
      
      if ($endsAt >= $start && $endsAt <= $end) {
        $results[] = $user->_id->__toString();
      }
    }
    
    return $results;
  }
  
  /**
   * @param  array{title:string, content:string, type:NotificationType, platforms:PlatformType[]}  $config
   * @param  array                                                                                 $emailConfig
   *
   * @return void
   * @throws ValidationException
   */
  public function sendNotification(array $config, array $emailConfig): void {
    $validator = Validator::make($config, [
      "title"       => "required|string",
      "content"     => "required|string",
      "coverImg"    => "nullable|string",
      "type"        => ["required", Rule::in(NotificationType::ALL)],
      "platforms"   => "array|min:1",
      "platforms.*" => [Rule::in([PlatformType::APP, PlatformType::PUSH, PlatformType::EMAIL])],
      "action"      => "required|array",
      "action.text" => "required|string",
      "action.link" => "required|string",
    ]);
    
    $data = $validator->validate();
    
    $createNotificationConfig = JobList::where("class", "App\Jobs\CreateNotification")->first();
    
    CreateNotification::dispatch([
      "title"     => $data["title"],
      "content"   => $data["content"],
      "app"       => AppType::CLUB,
      "type"      => $data["type"],
      "platforms" => $data["platforms"],
      "receivers" => [$this->toArray()],
      "action"    => [
        "text" => $data["action"]["text"],
        "link" => $data["action"]["link"],
      ],
      "extraData" => [ // data for email
        "actionLink" => $data["action"]["link"],
        "user"       => $this->only(["firstName", "lastName"]),
        ...$emailConfig
      ]
    ])->onQueue($createNotificationConfig->queueName);
  }
}
