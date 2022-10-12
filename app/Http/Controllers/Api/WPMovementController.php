<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClubPackType;
use App\Enums\HttpStatusCodes;
use App\Enums\MovementType;
use App\Exceptions\WpMovementHttpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawBySemesterRequest;
use App\Http\Requests\WithdrawRequest;
use App\Models\Movement;
use App\Models\SubModels\Semester;
use App\Models\User;
use App\Models\WPMovement;
use App\Rules\ObjectId;
use App\Traits\ValidatesRouteParameters;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WPMovementController extends Controller {
  use ValidatesRouteParameters;
  
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
   * @throws ValidationException
   */
  public function userSummaryBySemester($userId, $semesterId): array {
    $validatedData = validator([
      'semesterId' => $semesterId,
      'userId'     => $userId,
    ], [
      'semesterId' => 'required|string',
      'userId'     => ['required', new ObjectId()],
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
   * @throws ValidationException
   */
  public function userSummary(string $userId): array {
    $validatedData = validator([
      'userId' => $userId,
    ], [
      'userId' => ['required', new ObjectId()]
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
      'wpMovementId' => ['required', new ObjectId()],
    ])["wpMovementId"];
    /**
     * @var WPMovement $wpMovement
     */
    $wpMovement = WPMovement::findOrFail($wpMovementId);
    
    return $this->withdraw($wpMovement, $data["amount"], key_exists("userCardNum", $data) ? $data["userCardNum"] : null);
  }
  
  /**
   * @param  WithdrawBySemesterRequest  $request
   *
   * @return Collection
   */
  public function withdrawBySemester(WithdrawBySemesterRequest $request): Collection {
    $rules = $request->rules();
    
    /** @var User $authUser */
    $authUser = Auth::user();
    
    // If the user is admin, must provide the userId
    if ($authUser->isAdmin()) {
      $rules["userId"] = ['required', new ObjectId()];
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
  
}
