<?php

namespace App\Jobs;

use App\Models\SubModels\Semester;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TriggerEndSemesterSwitch implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  public array $data = ["semester" => null];
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct() {
    // Trigger end semester switch on the last expired semester
    $this->data["semester"] = Semester::getLastExpired()->id;
  }
  
  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle() {
    // job usato solo per il dispatch. Poi verrà invocato dall'app queue che invocherà una rotta dedicata
  }
}
