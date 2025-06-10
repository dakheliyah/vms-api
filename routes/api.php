<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\MumineenController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Mumineen API Routes
Route::prefix('mumineen')->group(function () {
    Route::get('/', [MumineenController::class, 'index']);
    Route::post('/', [MumineenController::class, 'store']);
    Route::get('/family-by-its-id/{its_id}', [MumineenController::class, 'getFamilyByItsId']);
    Route::get('/{its_id}', [MumineenController::class, 'show']);
    Route::put('/{its_id}', [MumineenController::class, 'update']);
    Route::delete('/{its_id}', [MumineenController::class, 'destroy']);
});
