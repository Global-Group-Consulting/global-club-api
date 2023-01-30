<?php

namespace App\Jobs;

use App\Enums\AppType;
use App\Enums\ClubPackType;
use App\Enums\NotificationType;
use App\Enums\PlatformType;
use App\Models\JobList;
use App\Models\SubModels\UserClubPackEntity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Env;
use Illuminate\Validation\ValidationException;

/**
 * Downgrade a user's pack to basic, usually because expired
 *
 * When doing this, inform the uer with a notification
 */
class DowngradeUserPack implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  public array $data;
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($userId) {
    $this->data["userId"] = $userId;
  }
  
  /**
   * Execute the job.
   *
   * @return void
   * @throws ValidationException
   */
  public function handle() {
    $user = User::findOrFail($this->data["userId"]);
    
    // if pack has changed or no lastChangedHistory exist, create a new UserClubPackEntity
    $lastChangeHistory = new UserClubPackEntity([
      "pack"     => ClubPackType::UNSUBSCRIBED,
      "startsAt" => Carbon::now(),
      "endsAt"   => null
    ]);
    
    // update the user pack
    $user->clubPack        = ClubPackType::UNSUBSCRIBED;
    $user->clubPackHistory = array_merge([$lastChangeHistory->getAttributes()], $user->clubPackHistory);
    $user->save();
    
    // send a notification to the user
    $user->sendNotification([
      "title"     => "Pacchetto Premium scaduto!",
      "content"   => "Il suo pacchetto premium è scaduto, pertanto è stato automaticamente passato al pacchetto base. Se desidera riattivare il pack premium, contatti il servizio clienti.",
      "type"      => NotificationType::CLUB_PACK_DOWNGRADE,
      "platforms" => PlatformType::ALL,
      "action"    => [
        "text" => "Riattivi il pacchetto premium",
        "link" => Env::get("APP_FRONTEND") . "/"
      ],
    ], []);
    
    return $user->clubPackHistory;
  }
  
}
