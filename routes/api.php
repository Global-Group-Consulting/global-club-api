<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});*/

Route::get('/hello', [\App\Http\Controllers\Api\WPMovementController::class, "hello"]);


Route::middleware('auth.cronUser')
  ->prefix("wp")
  ->group(function () {
    Route::get('/trigger-end-semester-switch', [\App\Http\Controllers\Api\WPMovementController::class, "triggerEndSemesterSwitch"]);
    
    Route::post("/add-brites-to-premium-wallet", [\App\Http\Controllers\Api\WPMovementController::class, "addBritesToPremiumWallet"]);
  });


Route::middleware('auth.customToken')
  ->prefix("wp")
  ->group(function () {
    Route::get('/{wpMovementId}', [\App\Http\Controllers\Api\WPMovementController::class, "show"]);
    
    Route::get('/user-summary/{userId}', [\App\Http\Controllers\Api\WPMovementController::class, "userSummary"]);
    Route::get('/user-summary-by-semester/{userId}/{semesterId}', [\App\Http\Controllers\Api\WPMovementController::class, "userSummaryBySemester"]);
    
    Route::post('/withdraw-by-semester', [\App\Http\Controllers\Api\WPMovementController::class, "withdrawBySemester"]);
    Route::post('/{wpMovementId}/withdraw', [\App\Http\Controllers\Api\WPMovementController::class, "withdrawById"]);
  });
