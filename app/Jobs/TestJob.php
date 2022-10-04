<?php

namespace App\Jobs;

use App\Enums\WPMovementType;
use App\Models\WPMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TestJob implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct() {
    //
  }
  
  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle() {
    $user     = \App\Models\User::find("5fceb07c64879300214f8de0");
    $movement = WPMovement::where("user_id", $user->id)
      ->where("movementType", WPMovementType::INITIAL_DEPOSIT)
      ->first();
    
    NotifyWPNewSemester::dispatchSync($user, $movement);
  }
}
