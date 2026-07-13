<?php
use App\Http\Controllers\Api\{AuthController,CardController,GameController,MemberController,MoveController,UserController}; use Illuminate\Support\Facades\Route;
Route::prefix('auth')->group(function () {
    Route::post('google', [AuthController::class, 'google']);
    Route::post('apple', [AuthController::class, 'apple']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
Route::apiResources(['users'=>UserController::class,'games'=>GameController::class,'members'=>MemberController::class,'cards'=>CardController::class,'moves'=>MoveController::class]);
Route::get('games/code/{code}',[GameController::class,'byCode']); Route::post('games/code/{code}/join',[GameController::class,'join']); Route::post('games/{game}/host-lobby-presence',[GameController::class,'setHostLobbyPresence']); Route::post('games/{game}/start',[GameController::class,'start']); Route::post('games/{game}/moves',[GameController::class,'move']); Route::post('games/{game}/finish-turn',[GameController::class,'finishTurn']); Route::post('games/{game}/pass-steal',[GameController::class,'passSteal']); Route::options('{path}',fn()=>response()->noContent())->where('path','.*');
