<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
  $phrases = \Illuminate\Support\Facades\Http::get("https://randomwordgenerator.com/json/phrases.json")->json();
  
  $random = rand(0, count($phrases["data"]) - 1);
  $phrase = $phrases["data"][$random];
  
  return view('index', [
    "phrase"  => $phrase["phrase"],
    "meaning" => $phrase["meaning"],
  ]);
});
