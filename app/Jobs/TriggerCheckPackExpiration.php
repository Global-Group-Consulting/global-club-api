<?php

namespace App\Jobs;

use App\Jobs\Syncronous\SendPackExpirationNotification;
use App\Models\JobList;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job that triggers the CheckPackExpiration job each day at 06:00
 */
class TriggerCheckPackExpiration implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  public array $data = [
    "expired"  => [],
    "expiring" => [
      "1month" => [],
      "1week"  => [],
      "1day"   => [],
    ],
  ];
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($data = null) {
    // nothing to do because all the work will be done in the handle() method
  }
  
  /**
   * Execute the job.
   *
   * @return array
   */
  public function handle(): array {
    // divide expiring users in 3 arrays based on the expiration date
    $this->data["expiring"] = User::getExpiringPremiumUsers();
    
    // Search for all users with expired Premium Pack
    $this->data["expired"] = User::getExpiredPremiumUsers()->pluck("_id")->map(fn($id) => $id->__toString())->toArray();
  
    $downgradeUserPackConfig = JobList::where('class', 'App\Jobs\DowngradeUserPack')->first();
    
    // handle expired users
    foreach ($this->data["expired"] as $userId) {
      // dispatch the job that will downgrade the user's pack
      DowngradeUserPack::dispatch($userId)->onQueue($downgradeUserPackConfig->queueName);
    }
    
    foreach ($this->data["expiring"] as $key => $users) {
      foreach ($users as $user) {
        // dispatch synchronously the job that will se  nd the notification to the user
        SendPackExpirationNotification::dispatchSync($user, $key);
      }
    }
    
    return $this->data;
  }
}
