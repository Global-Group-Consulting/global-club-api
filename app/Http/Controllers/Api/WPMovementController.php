<?php

namespace App\Http\Controllers\Api;

use App\Enums\WPMovementType;
use App\Exceptions\WpMovementException;
use App\Http\Controllers\Controller;
use App\Jobs\AddBritesToPremiumWallet;
use App\Models\JobList;
use App\Models\Movement;
use App\Models\SubModels\PremiumBySemesterEntry;
use App\Models\SubModels\Semester;
use App\Models\User;
use App\Models\WPMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MongoDB\BSON\ObjectId;

class WPMovementController extends Controller {
  public function hello() {
    return "Hello world!";
  }
  
  /**
   * @param $semesterId
   * @param $userId
   *
   * @return array
   */
  public function userSummaryBySemester($semesterId, $userId): array {
    $validatedData = validator([
      'semesterId' => $semesterId,
      'userId'     => $userId,
    ], [
      'semesterId' => 'required|string',
      'userId'     => ['required', new \App\Rules\ObjectId()],
    ])->validate();
    
    /**
     * @var User $user
     */
    // $authUser = Auth::user();
    $user = User::findOrFail($validatedData['userId']);
    
    //TODO:: check if the user can view the summary of the requested user
    
    return WPMovement::getSemesterSummary($validatedData["semesterId"], $user);
  }
  
  /**
   * Return the summary of all semesters for the given user.
   *
   * @param  string  $userId
   *
   * @return array
   */
  public function userSummary(string $userId): array {
    $validatedData = validator([
      'userId' => $userId,
    ], [
      'userId' => ['required', new \App\Rules\ObjectId()]
    ])->validate();
    
    $user           = User::findOrFail($validatedData['userId']);
    $validSemesters = Semester::getPastValidSemesters();
    $data           = [];
    
    //TODO:: check if the user can view the summary of the requested user
    
    foreach ($validSemesters as $semester) {
      $data[] = WPMovement::getSemesterSummary($semester["id"], $user, false);
    }
    
    return $data;
  }
  
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  // CRON USER ROUTES
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  
  /**
   * @param  Request  $request
   */
  public function triggerEndSemesterSwitch(Request $request) {
    $data = $request->validate([
      "semester" => "required|string",
    ]);
    
    $job       = JobList::where("class", "App\Jobs\AddBritesToPremiumWallet")->first();
    $movements = Movement::getPremiumBySemester($data["semester"]);
    
    $movements->each(function (PremiumBySemesterEntry $movement) use ($job) {
      // Adds to the queue a job for each user
      // This job will be handled by the "queue" app
      // that will make a http call to the local "api/wp/add-brites-to-premium-wallet" route
      AddBritesToPremiumWallet::dispatch($movement->toArray())->onQueue($job->queueName);
    });
  }
  
  /**
   * @param  Request  $request
   *
   * @return array
   * @throws WpMovementException
   */
  public function addBritesToPremiumWallet(Request $request): array {
    $data = new PremiumBySemesterEntry($request->validate([
      "userId"          => "required|string",
      "inAmount"        => "required|numeric",
      "outAmount"       => "required|numeric",
      "semester"        => "required|string",
      "remainingAmount" => "required|numeric",
    ]));
    $user = $data->user;
    
    // If no user is found, throw an exception
    if ( !$user) {
      throw new WpMovementException("User not found");
    }
    
    // If the user is not premium, will lose the brites, so we will not add them to the wallet
    if ( !$user->isPremium()) {
      throw new WpMovementException("User is no more premium");
    }
    
    // if the same user has already an initial movement for the same semester, avoid creating a new one
    // will not throw and exception, but will return a status "initial movement already exists" and the ids of the existing movements
    if ($user->walletPremiumMovements()
        ->where("semester", $data->semester)
        ->where("movementType", WPMovementType::INITIAL_DEPOSIT)
        ->count() > 0
    ) {
      $movementIds = $user->walletPremiumMovements()
        ->where("semester", $data->semester)->get()
        ->map(function (WPMovement $movement) {
          return $movement->_id;
        });
      
      return ["status"    => "initial movement already exists",
              "userId"    => $user->_id->__toString(),
              "movements" => $movementIds];
    }
    
    $semester         = Semester::parse($data->semester);
    $createdMovements = [];
    
    // Create initial deposit movement
    $createdMovements[] = $user->walletPremiumMovements()->create([
      "initialAmount" => $data->remainingAmount,
      "semester"      => $data->semester,
      "movementType"  => WPMovementType::INITIAL_DEPOSIT]);
    
    // Create 24 new movements for each future month
    for ($i = 0; $i < 24; $i++) {
      $percentage = ($data->remainingAmount * 4) / 100;
      
      // create the movement
      $createdMovements[] = $user->walletPremiumMovements()->create([
        "initialAmount"     => $data->remainingAmount,
        "incomeAmount"      => $percentage,
        "incomePercentage"  => 4,
        "semester"          => $data->semester,
        "withdrawableFrom"  => $semester->walletPremium->byMonthUsability[$i]["usableFrom"],
        "withdrawableUntil" => $semester->walletPremium->byMonthUsability[$i]["usableUntil"],
        "movementType"      => WPMovementType::MONTHLY_INCOME
      ]);
    }
    
    // create an array of only the ids of the created movements
    $movementIds = array_map(function ($movement) {
      return $movement["_id"];
    }, $createdMovements);
    
    return ["status"    => "ok",
            "userId"    => $user->_id->__toString(),
            "movements" => $movementIds];
  }
}
