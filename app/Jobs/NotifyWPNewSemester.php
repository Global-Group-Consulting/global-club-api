<?php

namespace App\Jobs;

use App\Enums\AppType;
use App\Enums\NotificationType;
use App\Enums\PlatformType;
use App\Models\JobList;
use App\Models\User;
use App\Models\WPMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Env;
use Illuminate\Validation\ValidationException;

class NotifyWPNewSemester implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  protected $user;
  protected $initialMovement;
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct(User $user, WPMovement $initialMovement) {
    $this->user            = $user;
    $this->initialMovement = $initialMovement;
  }
  
  /**
   * Execute the job.
   *
   * @return void
   * @throws ValidationException
   */
  public function handle() {
    $user            = $this->user;
    $initialMovement = $this->initialMovement;
    $job             = JobList::where("class", "App\Jobs\CreateNotification")->first();
    
    
    CreateNotification::dispatch([
      "title"     => "WP - Nuovo semestre aggiunto?",
      "content"   => "Sul suo Wallet Premium sono stati aggiunti i brite scaduti relativi al semestre {$initialMovement->semester}. Acceda al suo wallet per scoprire come li può utilizzare!",
      "app"       => AppType::CLUB,
      "type"      => NotificationType::WP_NEW_SEMESTER,
      "platforms" => PlatformType::ALL,
      "receivers" => [$user->toArray()],
      "action"    => [
        "text" => "Apri Wallet Premium",
        "link" => Env::get("APP_FRONTEND") . "/walletPremium?semester=" . $initialMovement->semester
      ],
      "extraData" => [ // data for email
        "user"       => $user->only(["firstName", "lastName"]),
        "semester"   => $initialMovement->semester,
        "actionLink" => Env::get("APP_FRONTEND") . "/walletPremium?semester=" . $initialMovement->semester
      ]
    ])->onQueue($job->queueName);
  }
}
