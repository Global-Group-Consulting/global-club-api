<?php

namespace App\Jobs;

use App\Enums\AppType;
use App\Enums\NotificationType;
use App\Enums\PlatformType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateNotification implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  protected function constrRules(): array {
    return [
      "title"                 => "required|string",
      "content"               => "required|string",
      "coverImg"              => "nullable|string",
      "app"                   => ['required', Rule::in([AppType::MAIN, AppType::CLUB, AppType::NEWS])],
      "type"                  => ["required", Rule::in(NotificationType::ALL)],
      "platforms"             => "array|min:1",
      "platforms.*"           => [Rule::in([PlatformType::APP, PlatformType::PUSH, PlatformType::EMAIL])],
      "receivers"             => "required|array|min:1",
      "receivers.*._id"       => "required|string",
      "receivers.*.firstName" => "required|string",
      "receivers.*.lastName"  => "required|string",
      "receivers.*.email"     => "required|email",
      "action"                => "required|array",
      "action.text"           => "required|string",
      "action.link"           => "required|string",
      "extraData"             => "nullable|array"
    ];
  }
  
  protected array $data;
  
  /**
   * Create a new job instance.
   *
   * @param  array{title:string, content: string}  $_data
   *
   * @return void
   * @throws ValidationException
   */
  public function __construct(array $_data) {
    $val = Validator::make($_data, $this->constrRules());
    $data = $val->validate();
    
    $this->data = $data;
  }
  
  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle(): void {
    // Job handled by news app
  }
}
