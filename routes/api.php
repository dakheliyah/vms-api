<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\MumineenController;
use App\Http\Controllers\API\VaazCenterController;
use App\Http\Controllers\API\BlockController;
use App\Http\Controllers\API\PassPreferenceController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication Routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Mumineen API Routes
Route::prefix('mumineen')->group(function () {
    Route::post('/', [MumineenController::class, 'store']);
    
    // Apply decryption middleware to routes that use its_id in GET query parameters
    Route::middleware('decrypt.its_id')->group(function () {
        Route::get('/', [MumineenController::class, 'indexOrShow']); // Using ?its_id=value or no params for index
        Route::get('/family-by-its-id', [MumineenController::class, 'getFamilyByItsId']); // Using ?its_id=value
    });
    
    Route::put('/{its_id}', [MumineenController::class, 'update']);
    Route::delete('/{its_id}', [MumineenController::class, 'destroy']);
});

// Vaaz Center API Routes (Protected)
Route::group(['middleware' => 'auth:api'], function () {
    Route::prefix('vaaz-centers')->group(function () {
    Route::get('/', [VaazCenterController::class, 'indexOrShow']);
    Route::post('/', [VaazCenterController::class, 'store']);
    Route::put('/', [VaazCenterController::class, 'update']);
    Route::delete('/', [VaazCenterController::class, 'destroy']);
    });
});

// Block API Routes (Protected)
Route::group(['middleware' => 'auth:api'], function () {
    Route::prefix('blocks')->group(function () {
    Route::get('/', [BlockController::class, 'index']);
    Route::post('/', [BlockController::class, 'store']);
    Route::put('/', [BlockController::class, 'update']);
    Route::delete('/', [BlockController::class, 'destroy']);
    });
});

// Pass Preference API Routes
Route::prefix('pass-preferences')->middleware('decrypt.its_id')->group(function () {
    Route::get('/', [PassPreferenceController::class, 'indexOrShow']);
    Route::get('/summary', [PassPreferenceController::class, 'summary']);
    Route::post('/', [PassPreferenceController::class, 'store']);
    Route::put('/', [PassPreferenceController::class, 'update']);
});

// Pass Preference API Routes (Destroy method protected)
Route::prefix('pass-preferences')->group(function () {
    Route::delete('/', [PassPreferenceController::class, 'destroy'])->middleware('auth:api');
});

// Event API Routes (Protected)
Route::group(['middleware' => 'auth:api'], function () {
    Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::post('/', [EventController::class, 'store']);
    Route::put('/', [EventController::class, 'update']);
    Route::delete('/', [EventController::class, 'destroy']);
    });
});
