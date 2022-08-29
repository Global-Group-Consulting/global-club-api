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

Route::middleware('auth.customToken')
  ->get('/hello', [\App\Http\Controllers\Api\WPMovementController::class, "hello"]);


Route::middleware('auth.cronUser')
  ->prefix("wp")
  ->group(function () {
    Route::get('/trigger-end-semester-switch', [\App\Http\Controllers\Api\WPMovementController::class, "triggerEndSemesterSwitch"]);
    
    Route::post("/add-brites-to-premium-wallet", [\App\Http\Controllers\Api\WPMovementController::class, "addBritesToPremiumWallet"]);
  });
