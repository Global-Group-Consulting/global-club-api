<?php

namespace App\Jobs;

use App\Enums\AppType;
use App\Enums\NotificationType;
use App\Enums\PlatformType;
use App\Enums\WPMovementType;
use App\Models\JobList;
use App\Models\User;
use App\Models\WPMovement;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Env;

/**
 * Controlla se l'utente non ha ancora sbloccato i brite disponibili per questo mese
 * Farlo 5gg e 1gg prima della ricapitalizzazione
 */
class NotifyWPBeforeRecapitalization implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  public array $data = ["userIds" => null];
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct() {
    // brite mese corrente, che non sono ancora riscossi
    $data = WPMovement::where('movementType', WPMovementType::MONTHLY_INCOME)
      ->where('withdrawableFrom', '<=', Carbon::now())
      ->where('withdrawableUntil', '>=', Carbon::now())
      ->where('withdrawalRemaining', '>', 0)
      ->groupBy('userId')
      ->get();
    
    $this->data = [
      "userIds" => $data->map(function ($item) {
        return $item["_id"]["userId"]->__toString();
      })->toArray()
    ];
  }
  
  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle(): void {
    // Job handled by the queue app that will call WPMovementController->notifyWPBeforeRecapitalization
  }
  
  
}
