<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AutobotController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/bot', [AutobotController::class, 'index']);
Route::get('/getuser', [AutobotController::class, 'getUsers']);
