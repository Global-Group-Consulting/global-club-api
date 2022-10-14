<?php

namespace App\Jobs\Syncronous;

use App\Enums\NotificationType;
use App\Enums\PlatformType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Env;
use Illuminate\Validation\ValidationException;

class SendPackExpirationNotification implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  public User $user;
  /**
   * @var string 1month | 1week | 1day
   */
  public string $expiresIn;
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($userId, $expiresIn) {
    $this->user      = User::findOrFail($userId);
    $this->expiresIn = $expiresIn;
  }
  
  /**
   * Execute the job.
   *
   * @return void
   * @throws ValidationException
   */
  public function handle(): void {
    $expirationDate = new Carbon($this->user->clubPackHistory[0]["endsAt"]->toDateTime());
    
    $expiresInString = "";
    
    switch ($this->expiresIn) {
      case "1month":
        $expiresInString = "tra 1 mese";
        break;
      case "1week":
        $expiresInString = "tra 1 settimana";
        break;
      case "1day":
        $expiresInString = "domani";
        break;
    }
    
    $msg = "Il suo pacchetto Premium scadrà " . $expiresInString . ", il " . $expirationDate->format("d/m/Y") . ".";
    $msg .= " Per non perdere i vantaggi relativi, rinnovi il pacchetto prima della scadenza.";
    
    $this->user->sendNotification([
      "title"     => "Pacchetto Premium in scadenza!",
      "content"   => $msg,
      "type"      => NotificationType::CLUB_PACK_EXPIRING,
      "platforms" => PlatformType::ALL,
      "action"    => [
        "text" => "Rinnova il pacchetto premium",
        "link" => Env::get("APP_FRONTEND") . "/"
      ],
    ], [
      "expiresIn"      => $expiresInString,
      "expirationDate" => $expirationDate->format("d/m/Y"),
    ]);
  }
}
