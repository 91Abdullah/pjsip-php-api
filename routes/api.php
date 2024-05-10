<?php

use App\Http\Controllers\APIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/new_login', [LoginController::class, 'newLogin']);
Route::put('/change_password', [APIController::class, 'changePassword']);
Route::get('/cdrs', [APIController::class, 'getCdrsByFilters']);
Route::get('/accounts', [APIController::class, 'getAccounts']);
Route::get('/accounts/{extension}', [APIController::class, 'getAccount']);
Route::post('/accounts/{extension}', [APIController::class, 'createAccount']);
Route::delete('/accounts/{extension}', [APIController::class, 'deleteAccount']);
Route::get('/download_recording/{date}/{recordingfile}', [APIController::class, 'downloadRecording']);