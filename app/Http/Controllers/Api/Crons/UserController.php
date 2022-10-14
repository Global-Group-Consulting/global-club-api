<?php

namespace App\Http\Controllers\Api\Crons;

use App\Http\Controllers\Controller;
use App\Jobs\DowngradeUserPack;
use App\Rules\ObjectId;
use Illuminate\Http\Request;

class UserController extends Controller {
  public function downgradePack(Request $request) {
    $data = $request->validate([
      "userId" => ["required", new ObjectId],
    ]);
    
    return (new DowngradeUserPack($data["userId"]))->handle();
  }
}
