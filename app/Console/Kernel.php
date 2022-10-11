<?php

namespace App\Console;

use App\Jobs\NotifyWPBeforeRecapitalization;
use App\Jobs\TriggerEndSemesterSwitch;
use App\Models\JobList;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel {
  
  
  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   *
   * @return void
   */
  protected function schedule(Schedule $schedule) {
    // $schedule->command('inspire')->hourly();
    
    $notifyWPBeforeRecapitalization = JobList::where('class', 'App\Jobs\NotifyWPBeforeRecapitalization')->first();
    $triggerEndSemesterSwitch       = JobList::where('class', 'App\Jobs\TriggerEndSemesterSwitch')->first();
    
    try {
      // 5gg prima di ricapitalizzazione - ricorda che ci sono ancora brite da sbloccare
      // il giorno della ricapitalizzazione - ricorda che ci sono ancora brite da sbloccare
      $schedule->job(new NotifyWPBeforeRecapitalization(), $notifyWPBeforeRecapitalization->queueName)->twiceMonthly(10, 15, '06:00');
    } catch (\Exception $e) {
      $message = "Missing configuration for NotifyWPBeforeRecapitalization";
      
      dump($message);
      Log::error($message);
    }
    
    try {
      
      // A gennaio e a luglio controlla i brite scaduti e li sposta eventualmente sul wallet premium
      $schedule->job(new TriggerEndSemesterSwitch(), $triggerEndSemesterSwitch->queueName)->cron('0 0 1 1,7 *');
    } catch (\Exception $e) {
      $message = "Missing configuration for TriggerEndSemesterSwitch";
      
      dump($message);
      Log::error($message);
    }
    
    // Ultimo del mese - Se utente non più premium, ricorda che non potrà sbloccare i brite
    // Non la faccio più, perché non è più necessaria. Uso sempre la notifica "NotifyWPBeforeRecapitalization"
    // Che informa dei brite disponibili
    // $schedule->job(new NotifyWPCheckPremium())->everyMinute();
  }
  
  /**
   * Register the commands for the application.
   *
   * @return void
   */
  protected function commands() {
    $this->load(__DIR__ . '/Commands');
    
    require base_path('routes/console.php');
  }
}
