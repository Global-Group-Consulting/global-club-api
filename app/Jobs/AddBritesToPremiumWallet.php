<?php

namespace App\Jobs;

use App\Enums\WPMovementType;
use App\Helpers\Semester;
use App\Models\SubModels\PremiumBySemesterEntry;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddBritesToPremiumWallet implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  /**
   * @var mixed
   */
  protected mixed $data;
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($data) {
//    $movements = Movement::getPremiumBySemester("2021_1");

//    $this->data = $movements->first();
    $this->data = $data;
  }
  
  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle(): void {
    // job will be handled by the queue app
  }
}
