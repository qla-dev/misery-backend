<?php
use App\Http\Controllers\Api\{CardController,GameController,MemberController,MoveController,UserController}; use Illuminate\Support\Facades\Route;
Route::apiResources(['users'=>UserController::class,'games'=>GameController::class,'members'=>MemberController::class,'cards'=>CardController::class,'moves'=>MoveController::class]);
Route::get('games/code/{code}',[GameController::class,'byCode']); Route::post('games/code/{code}/join',[GameController::class,'join']); Route::post('games/{game}/start',[GameController::class,'start']); Route::post('games/{game}/moves',[GameController::class,'move']); Route::options('{path}',fn()=>response()->noContent())->where('path','.*');
