<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\MumineenController;
use App\Http\Controllers\API\VaazCenterController;
use App\Http\Controllers\API\BlockController;
use App\Http\Controllers\API\PassPreferenceController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\MiqaatController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AccommodationController;
use App\Http\Controllers\API\HizbeSaifeeGroupController;

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

// Mumineen API Routes
Route::prefix('mumineen')->middleware('decrypt.its_id')->group(function () {
    Route::post('/', [MumineenController::class, 'store']);
    
    Route::get('/', [MumineenController::class, 'indexOrShow']); // Using ?its_id=value or no params for index
    Route::get('/family-by-its-id', [MumineenController::class, 'getFamilyByItsId']); // Using ?its_id=value
    
    Route::delete('/{its_id}', [MumineenController::class, 'destroy']);

    // Mumineen by Event Route
    Route::get('/pass-preference/breakdown', [MumineenController::class, 'getMumineenWithPassesByEvent']);
    
    // Route to download sample CSV for Mumineen bulk upload
    Route::get('/sample-csv', [MumineenController::class, 'downloadSampleCsv']);
    // Mumineen Bulk Upload
    Route::post('/bulk', [MumineenController::class, 'bulkStore']);
    Route::post('/auto-assign-groups', [MumineenController::class, 'autoAssignHizbeSaifeeGroups']);
});

// Protected API Routes
// Public Pass Preference Routes (No JWT Auth required, but can use Token header)
Route::prefix('pass-preferences')->middleware('decrypt.its_id')->group(function () {
    Route::get('/', [PassPreferenceController::class, 'indexOrShow']);
    Route::post('/', [PassPreferenceController::class, 'store']);
    Route::put('/', [PassPreferenceController::class, 'update']);
    Route::put('vaaz-center', [PassPreferenceController::class, 'updateVaazCenter']);
    Route::post('vaaz-center', [PassPreferenceController::class, 'storeVaazCenterPreference']);
    Route::put('pass-type', [PassPreferenceController::class, 'updatePassType']);
    Route::post('pass-type', [PassPreferenceController::class, 'storePassTypePreference']);
    Route::delete('/', [PassPreferenceController::class, 'destroy']);
    Route::get('/summary', [PassPreferenceController::class, 'summary']);
    Route::get('/vaaz-center-summary', [PassPreferenceController::class, 'vaazCenterSummary']);
    Route::put('lock-preferences', [PassPreferenceController::class, 'bulkUpdateLockStatus']);
    Route::put('bulk-assign-vaaz-center', [PassPreferenceController::class, 'bulkAssignVaazCenter']);
});


// Hizbe Saifee Group API Routes
Route::group(['prefix' => 'hizbe-saifee-groups', 'middleware' => 'decrypt.its_id'], function () {
    Route::get('/', [HizbeSaifeeGroupController::class, 'index']);
    Route::post('/', [HizbeSaifeeGroupController::class, 'store']); // Handles bulk store
    Route::get('/{hizbeSaifeeGroup}', [HizbeSaifeeGroupController::class, 'show']);
    Route::put('/', [HizbeSaifeeGroupController::class, 'update']);    // Handles bulk update
    Route::delete('/{hizbeSaifeeGroup}', [HizbeSaifeeGroupController::class, 'destroy']);
});

// Admin Activity Log Route
Route::get('/admin/activity-logs', [\App\Http\Controllers\API\ActivityLogController::class, 'getAdminActions'])->middleware('decrypt.its_id');

Route::group(['middleware' => 'auth:api'], function () {
    // Miqaat API Routes
    Route::apiResource('miqaats', MiqaatController::class);

    // Event API Routes
    Route::apiResource('events', EventController::class);

    // Vaaz Center API Routes
    Route::prefix('vaaz-centers')->group(function () {
        Route::get('/', [VaazCenterController::class, 'indexOrShow']);
        Route::post('/', [VaazCenterController::class, 'store']);
        Route::put('/', [VaazCenterController::class, 'update']);
        Route::delete('/', [VaazCenterController::class, 'destroy']);
    });

    // Block API Routes
    Route::prefix('blocks')->group(function () {
        Route::get('/', [BlockController::class, 'index']);
        Route::post('/', [BlockController::class, 'store']);
        Route::put('/', [BlockController::class, 'update']);
        Route::delete('/', [BlockController::class, 'destroy']);
    });

    // Accommodation API Routes
    Route::apiResource('accommodations', AccommodationController::class);

});
