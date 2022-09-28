<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClubPackType;
use App\Enums\HttpStatusCodes;
use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Enums\WPMovementType;
use App\Exceptions\WpMovementHttpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawBySemesterRequest;
use App\Http\Requests\WithdrawRequest;
use App\Jobs\AddBritesToPremiumWallet;
use App\Jobs\CreateNotification;
use App\Jobs\NotifyWPNewSemester;
use App\Models\JobList;
use App\Models\Movement;
use App\Models\SubModels\PremiumBySemesterEntry;
use App\Models\SubModels\Semester;
use App\Models\User;
use App\Models\WPMovement;
use App\Traits\ValidatesRouteParameters;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use MongoDB\BSON\ObjectId;

class WPMovementController extends Controller {
  use ValidatesRouteParameters;
  
  public function hello() {
    return "Hello world!";
  }
  
  /**
   * @param  string  $movementId
   *
   * @return WPMovement
   */
  public function show(string $movementId): WPMovement {
    return WPMovement::findOrFail($movementId);
  }
  
  /**
   * @param $userId
   * @param $semesterId
   *
   * @return array
   */
  public function userSummaryBySemester($userId, $semesterId): array {
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
      $semesterSummary = WPMovement::getSemesterSummary($semester["id"], $user, false);
      
      // return only the semesters that have an initial amount or a movement
      if ($semesterSummary["initialAmount"] > 0) {
        $data[] = $semesterSummary;
      }
    }
    
    return $data;
  }
  
  /**
   * @param  WithdrawRequest  $request
   * @param  string           $_wpMovementId
   *
   * @return WPMovement
   * @throws WpMovementHttpException|Exception
   */
  public function withdrawById(WithdrawRequest $request, string $_wpMovementId): WPMovement {
    $data = $request->validated();
    
    $wpMovementId = $this->validateRouteParams([
      'wpMovementId' => $_wpMovementId,
    ], [
      'wpMovementId' => ['required', new \App\Rules\ObjectId()],
    ])["wpMovementId"];
    /**
     * @var WPMovement $wpMovement
     */
    $wpMovement = WPMovement::findOrFail($wpMovementId);
    
    return $this->withdraw($wpMovement, $data["amount"], key_exists("userCardNum", $data) ? $data["userCardNum"] : null);
  }
  
  /**
   * @param  WithdrawBySemesterRequest  $request
   */
  public function withdrawBySemester(WithdrawBySemesterRequest $request) {
    $rules = $request->rules();
    
    /** @var User $authUser */
    $authUser = Auth::user();
    
    // If the user is admin, must provide the userId
    if ($authUser->isAdmin()) {
      $rules["userId"] = ['required', new \App\Rules\ObjectId()];
    }
    
    $data = $request->validate($rules);
    // If the user is admin, fetch the requested user, otherwise use the logged one
    $user = $authUser->isAdmin() ? User::findOrFail($data["userId"]) : $authUser;
    
    // Get the movements will be handled for the given semesters
    $movements = WPMovement::where("withdrawableFrom", "<", Carbon::now())
      ->where("withdrawableUntil", ">", Carbon::now())
      // if the request is made by an admin, must specify the userId
      ->where("userId", $user->_id)
      ->whereIn("semester", $data["semesters"])
      ->get();
    
    $totalAvailable    = $movements->sum("withdrawalRemaining");
    $multipleSemesters = count($data["semesters"]) > 1;
    
    if ( !$movements->count() || ($multipleSemesters && $totalAvailable !== (float) $data["amount"])) {
      throw new WpMovementHttpException(HttpStatusCodes::HTTP_BAD_REQUEST, "Invalid amount specified");
    }
    
    return $movements->map(function (WPMovement $movement) use ($data, $multipleSemesters) {
      return $this->withdraw($movement, ($multipleSemesters ? $movement->withdrawalRemaining : $data["amount"]), (key_exists("userCardNum", $data) ? $data["userCardNum"] : null));
    });
  }
  
  /**
   * @param  WPMovement   $wpMovement
   * @param  float        $amount
   * @param  string|null  $userCardNum
   *
   * @return WPMovement
   * @throws Exception
   */
  public function withdraw(WPMovement $wpMovement, float $amount, string $userCardNum = null): WPMovement {
    $isTransfer = false;
    
    /** @var User $user */
    $user = Auth::user();
    
    $withdrawForAnotherUser = $wpMovement->user->_id != $user->_id;
    $transferToSelf         = $userCardNum && $userCardNum === $user->clubCardNumber;
    
    if (($withdrawForAnotherUser && !$user->isAdmin())) {
      throw new WpMovementHttpException(HttpStatusCodes::HTTP_FORBIDDEN, "You are not allowed to withdraw from this wallet.");
    }
    
    if ($transferToSelf) {
      throw new WpMovementHttpException(HttpStatusCodes::HTTP_BAD_REQUEST, "You must specify a different club card number that your own.");
    }
    
    // by default, the user is withdrawing from his own wallet
    $destinationUser = $user;
    
    
    // If there is a userCardNum I must transfer the money to that user's account
    if (isset($userCardNum)) {
      // fetch the user with that card number
      $destinationUser = User::where("clubCardNumber", $userCardNum)->first();
      
      // throw an error if the user does not exist
      if ( !$destinationUser) {
        throw new WpMovementHttpException(HttpStatusCodes::HTTP_NOT_FOUND, "No user found with the provided card number: $userCardNum");
      }
      
      $isTransfer = true;
    }
    
    // check if the user has enough money to withdraw and the movement is still withdrawable
    $wpMovement->checkIsWithdrawable($amount);
    
    $date = Carbon::now()->locale('it_IT');
    
    // generate a new movement for the destination user
    $movement = new Movement([
      "userId"       => $destinationUser->_id,
      "amountChange" => $amount,
      "movementType" => $isTransfer ? MovementType::DEPOSIT_RECEIVED_WP : MovementType::DEPOSIT_UNLOCKED_WP,
      "semesterId"   => Semester::getPrevSemester()->id,
      "clubPack"     => ClubPackType::PREMIUM,
      "createdBy"    => $user->_id,
      "fromUUID"     => null,
      "notes"        => $isTransfer ?
        "Wallet Premium - Trasferimento da {$user->getFullName()}" . ($user->clubCardNumber ? " ($user->clubCardNumber)" : '')
        : "Wallet Premium - Brite sbloccati per il mese di {$date->translatedFormat('F')}",
      "order"        => null,
    ]);
    
    // once created the movement, create an internal movement for the wpMovement
    // Store the movement id in the wpMovement
    try {
      $movement->save();
      
      $wpMovement->addWithdrawMovement($movement);
      
      return WPMovement::findOrFail($wpMovement->_id)->makeHidden(["withdrawalMovements"]);
    } catch (Exception $e) {
      if (isset($movement->_id)) {
        $movement->delete();
      }
      
      throw $e;
    }
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
      "userIds"  => "nullable|array"
    ]);
    
    $job       = JobList::where("class", "App\Jobs\AddBritesToPremiumWallet")->first();
    $movements = Movement::getPremiumBySemester($data["semester"], $data["userIds"] ?? null);
    
    if ( !$movements->count()) {
      throw new WpMovementHttpException(HttpStatusCodes::HTTP_BAD_REQUEST, "No movements found for the given semester and user");
    }
    
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
   * @throws WpMovementHttpException
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
      throw new WpMovementHttpException("User not found");
    }
    
    // If the user is not premium, will lose the brites, so we will not add them to the wallet
    if ( !$user->isPremium()) {
      throw new WpMovementHttpException("User is no more premium");
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
    
    // Notify the user that the brites have been added to the wallet
    NotifyWPNewSemester::dispatchSync($user, $createdMovements[0]);
    
    return ["status"    => "ok",
            "userId"    => $user->_id->__toString(),
            "movements" => $movementIds];
  }
}
