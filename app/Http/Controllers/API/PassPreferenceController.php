<?php

namespace App\Http\Controllers\API;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Helpers\AuthorizationHelper;
use App\Enums\PassPreferenceErrorCode;
use App\Models\PassPreference;
use App\Models\VaazCenter;
use App\Models\Block;
use App\Models\Event; // Added Event model
use App\Models\Mumineen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Enums\PassType;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Pass Preferences",
 *     description="API Endpoints for managing Pass Preferences"
 * )
 */
class PassPreferenceController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/pass-preferences/summary",
     *      operationId="getPassPreferenceSummary",
     *      tags={"Pass Preferences"},
     *      summary="Get pass availability summary for an event",
     *      description="Returns a summary of pass availability, including Vaaz centers and their blocks, for a specific event.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="event_id",
     *          in="query",
     *          description="ID of the event to get summary for",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="vaaz_center_id",
     *          in="query",
     *          description="Optional ID of the Vaaz Center to filter summary for",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", description="Vaaz Center ID"),
     *                  @OA\Property(property="name", type="string", description="Vaaz Center Name"),
     *                  @OA\Property(property="vaaz_center_capacity", type="integer", description="Capacity of the Vaaz Center"),
     *                  @OA\Property(property="vaaz_center_issued_passes", type="integer", description="Number of passes issued for the Vaaz Center"),
     *                  @OA\Property(property="vaaz_center_availability", description="Availability in Vaaz Center (number or 'unlimited')"),
     *                  @OA\Property(property="blocks", type="array", @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", description="Block ID"),
     *                      @OA\Property(property="type", type="string", description="Block Type/Name"),
     *                      @OA\Property(property="capacity", type="integer", description="Capacity of the Block"),
     *                      @OA\Property(property="gender", type="string", enum={"Male", "Female", "All"}, description="Gender for the Block"),
     *                      @OA\Property(property="min_age", type="integer", nullable=true, description="Minimum age for the Block"),
     *                      @OA\Property(property="max_age", type="integer", nullable=true, description="Maximum age for the Block"),
     *                      @OA\Property(property="block_issued_passes", type="integer", description="Number of passes issued for the Block"),
     *                      @OA\Property(property="block_availability", description="Availability in Block (number or 'unlimited')")
     *                  ))
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(type="object", example={"event_id": {"The event id field is required."}})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="The specified Vaaz Center was not found for the given event.",
     *          @OA\JsonContent(type="object", example={"message": "The specified Vaaz Center was not found for the given event."})
     *      )
     * )
     * Provide a summary of pass availability for a given event.
     */
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
            'vaaz_center_id' => 'nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $eventId = $request->input('event_id');
        $vaazCenterId = $request->input('vaaz_center_id');

        $vaazCentersQuery = VaazCenter::where('event_id', $eventId);

        if ($vaazCenterId) {
            $vaazCentersQuery->where('id', $vaazCenterId);
        }

        $vaazCenters = $vaazCentersQuery->withCount('passPreferences') // Count passes directly associated with the VaazCenter
            ->with(['blocks' => function ($query) {
                $query->withCount('passPreferences'); // Count passes for each block
            }])
            ->get();

        if ($vaazCenterId && $vaazCenters->isEmpty()) {
            return response()->json(['message' => 'The specified Vaaz Center was not found for the given event.'], 404);
        }

        $summary = $vaazCenters->map(function ($vaazCenter) {
            $vaazCenterIssuedPasses = $vaazCenter->pass_preferences_count ?? 0;
            // If est_capacity is 0 or null, consider it unlimited for availability calculation
            $vaazCenterCapacity = $vaazCenter->est_capacity ?? 0;
            $vaazCenterAvailability = ($vaazCenterCapacity > 0) ? ($vaazCenterCapacity - $vaazCenterIssuedPasses) : 'unlimited';

            return [
                'id' => $vaazCenter->id,
                'name' => $vaazCenter->name,
                'vaaz_center_capacity' => $vaazCenterCapacity,
                'vaaz_center_issued_passes' => $vaazCenterIssuedPasses,
                'vaaz_center_availability' => $vaazCenterAvailability,
                'blocks' => $vaazCenter->blocks->map(function ($block) {
                    $blockIssuedPasses = $block->pass_preferences_count ?? 0;
                    // If block capacity is 0 or null, consider it unlimited
                    $blockCapacity = $block->capacity ?? 0;
                    $blockAvailability = ($blockCapacity > 0) ? ($blockCapacity - $blockIssuedPasses) : 'unlimited';
                    return [
                        'id' => $block->id,
                        'type' => $block->type,
                        'capacity' => $blockCapacity,
                        'gender' => $block->gender,
                        'min_age' => $block->min_age,
                        'max_age' => $block->max_age,
                        'block_issued_passes' => $blockIssuedPasses,
                        'block_availability' => $blockAvailability,
                    ];
                }),
            ];
        });

        return response()->json($summary);
    }

    /**
     * @OA\Get(
     *      path="/api/pass-preferences/vaaz-center-summary",
     *      operationId="getVaazCenterPassPreferenceSummary",
     *      tags={"Pass Preferences"},
     *      summary="Get Vaaz Center pass availability summary for an event",
     *      description="Returns a summary of pass availability for Vaaz centers, for a specific event. Does not include block details.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="event_id",
     *          in="query",
     *          description="ID of the event to get summary for",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="vaaz_center_id",
     *          in="query",
     *          description="Optional ID of the Vaaz Center to filter summary for",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", description="Vaaz Center ID"),
     *                  @OA\Property(property="name", type="string", description="Vaaz Center Name"),
     *                  @OA\Property(property="vaaz_center_capacity", type="integer", description="Capacity of the Vaaz Center"),
     *                  @OA\Property(property="vaaz_center_issued_passes", type="integer", description="Number of passes issued for the Vaaz Center"),
     *                  @OA\Property(property="vaaz_center_availability", description="Availability in Vaaz Center (number or 'unlimited')")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="The specified Vaaz Center was not found for the given event.")
     * )
     */
    public function vaazCenterSummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
            'vaaz_center_id' => 'nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $eventId = $request->input('event_id');
        $vaazCenterId = $request->input('vaaz_center_id');

        $vaazCentersQuery = VaazCenter::where('event_id', $eventId);

        if ($vaazCenterId) {
            $vaazCentersQuery->where('id', $vaazCenterId);
        }

        $vaazCenters = $vaazCentersQuery->withCount([
            'passPreferences', // Total issued passes, will be 'pass_preferences_count'
            'passPreferences as male_issued_passes' => function ($query) {
                $query->whereHas('mumineen', function ($subQuery) {
                    $subQuery->where('gender', 'male'); // Assuming 'male' is the value in Mumineen table
                });
            },
            'passPreferences as female_issued_passes' => function ($query) {
                $query->whereHas('mumineen', function ($subQuery) {
                    $subQuery->where('gender', 'female'); // Assuming 'female' is the value in Mumineen table
                });
            }
        ])
            ->get();

        if ($vaazCenterId && $vaazCenters->isEmpty()) {
            return response()->json(['message' => 'The specified Vaaz Center was not found for the given event.'], 404);
        }

        $summary = $vaazCenters->map(function ($vaazCenter) {
            // Overall capacity (est_capacity)
            $totalIssuedPasses = $vaazCenter->pass_preferences_count ?? 0;
            $totalCapacity = $vaazCenter->est_capacity; // Keep as is, could be null
            $totalAvailability = isset($totalCapacity) && $totalCapacity > 0 ? ($totalCapacity - $totalIssuedPasses) : (isset($totalCapacity) ? 0 : 'unlimited');

            // Male capacity
            $maleIssuedPasses = $vaazCenter->male_issued_passes ?? 0;
            $maleCapacity = $vaazCenter->male_capacity; // Could be null
            $maleAvailability = isset($maleCapacity) && $maleCapacity > 0 ? ($maleCapacity - $maleIssuedPasses) : (isset($maleCapacity) ? 0 : 'Not Set');

            // Female capacity
            $femaleIssuedPasses = $vaazCenter->female_issued_passes ?? 0;
            $femaleCapacity = $vaazCenter->female_capacity; // Could be null
            $femaleAvailability = isset($femaleCapacity) && $femaleCapacity > 0 ? ($femaleCapacity - $femaleIssuedPasses) : (isset($femaleCapacity) ? 0 : 'Not Set');

            return [
                'id' => $vaazCenter->id,
                'name' => $vaazCenter->name,
                'total_capacity' => $totalCapacity ?? 'Not Set',
                'total_issued_passes' => $totalIssuedPasses,
                'total_availability' => $totalAvailability,

                'male_capacity' => $maleCapacity ?? 'Not Set',
                'male_issued_passes' => $maleIssuedPasses,
                'male_availability' => $maleAvailability,

                'female_capacity' => $femaleCapacity ?? 'Not Set',
                'female_issued_passes' => $femaleIssuedPasses,
                'female_availability' => $femaleAvailability,
            ];
        });

        return response()->json($summary);
    }

    /**
     * @OA\Get(
     *      path="/api/pass-preferences",
     *      operationId="getPassPreferences",
     *      tags={"Pass Preferences"},
     *      summary="Get a specific pass preference by ITS ID",
     *      description="Returns a single pass preference if an ITS ID is provided. The user must be authorized to view the preference for the given ITS ID. Listing all preferences is restricted and will return an empty array.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="query",
     *          description="Encrypted ITS ID of the mumineen to retrieve a specific pass preference. If not provided, returns an empty array.",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation. Returns a single PassPreference object or an empty array.",
     *          @OA\JsonContent(
     *              type="object",
     *              example={"id": 1, "its_id": 12345, "event_id": 1, "pass_type": "RAHAT", "block_id": 1, "vaaz_center_id": 1, "created_at": "2023-01-01T00:00:00.000000Z", "updated_at": "2023-01-01T00:00:00.000000Z", "block": {"id": 1, "name": "Block A"}, "vaazCenter": {"id": 1, "name": "Center 1"}, "event": {"id": 1, "name": "Event Name"}}
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden. User is not authorized to view this preference.",
     *          @OA\JsonContent(type="object", example={"message": "You are not authorized to view this pass preference."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Pass Preference not found for the given ITS ID",
     *          @OA\JsonContent(type="object", example={"message": "Pass Preference not found for this ITS number"})
     *      )
     * )
     * Display a listing of the resource.
     */
    public function indexOrShow(Request $request)
    {
        $targetItsId = $request->input('user_decrypted_its_id');

        // Get all family ITS IDs
        $familyItsIds = $this->getFamilyItsIds($targetItsId);
        if (empty($familyItsIds)) {
            return response()->json(['message' => 'Could not determine family members.'], 404);
        }

        // Fetch all pass preferences for the entire family
        $passPreferences = PassPreference::whereIn('its_id', $familyItsIds)
            ->with(['block', 'vaazCenter', 'event'])
            ->get();

        if ($passPreferences->isEmpty()) {
            return response()->json(['message' => 'No pass preferences found for this family.'], 404);
        }

        return response()->json($passPreferences);
    }

    /**
     * @OA\Post(
     *      path="/api/pass-preferences",
     *      operationId="storePassPreference",
     *      tags={"Pass Preferences"},
     *      summary="Create one or more new pass preferences",
     *      description="Creates one or more new pass preference records in a single transaction. The ITS ID is expected to be encrypted.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="An array of pass preference objects to create.",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  required={"its_id", "event_id"},
     *                  @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen. Must be unique."),
     *                  @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *                  @OA\Property(property="pass_type", type="string", enum={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}, nullable=true, description="Type of pass"),
     *                  @OA\Property(property="block_id", type="integer", nullable=true, description="ID of the block"),
     *                  @OA\Property(property="vaaz_center_id", type="integer", nullable=true, description="ID of the Vaaz center")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Pass preferences created successfully",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(type="object", example={"id": 1, "its_id": 12345, "event_id": 1, "pass_type": "RAHAT", "block_id": 1, "vaaz_center_id": 1, "created_at": "2023-01-01T00:00:00.000000Z", "updated_at": "2023-01-01T00:00:00.000000Z"})
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden. User is not authorized to create a preference for one or more ITS numbers.",
     *          @OA\JsonContent(type="object", example={"message": "Authorization failed for one or more ITS numbers."})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error or business logic error (e.g., capacity full, incorrect associations)",
     *          @OA\JsonContent(type="object", example={"message": "The selected block is full for ITS 12345"})
     *      )
     * )
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $preferencesData = $request->all();

        if (!is_array($preferencesData) || empty($preferencesData) || !isset($preferencesData[0])) {
            return response()->json(['message' => 'Request body must be a non-empty array of pass preferences.'], 400);
        }

        $validator = Validator::make($preferencesData, [
            '*.its_id' => ['required', 'integer', 'distinct', 
                function ($attribute, $value, $fail) {
                    $existingPreference = PassPreference::where('its_id', $value)->first();
                    if ($existingPreference && $existingPreference->is_locked) {
                        $fail("The pass preference for ITS ID {$value} is locked and cannot be changed.");
                    } elseif ($existingPreference) {
                        $fail("A pass preference for ITS ID {$value} already exists. Use the update endpoint instead.");
                    }
                }
            ],
            '*.event_id' => 'required|integer|exists:events,id',
            '*.pass_type' => ['nullable', Rule::enum(PassType::class)],
            '*.block_id' => 'sometimes|nullable|integer|exists:blocks,id',
            '*.vaaz_center_id' => 'sometimes|nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedPreferences = $validator->validated();
        $createdPreferences = [];

        foreach ($validatedPreferences as $preferenceData) {
            if (!$this->isFamilyMember($request, $preferenceData['its_id'])) {
                return response()->json(['message' => 'Authorization failed for one or more ITS numbers.'], 403);
            }
        }

        try {
            DB::transaction(function () use ($validatedPreferences, &$createdPreferences) {
                foreach ($validatedPreferences as $preferenceData) {
                    $targetEventId = $preferenceData['event_id'];

                    if (isset($preferenceData['vaaz_center_id'])) {
                        $vaazCenter = VaazCenter::find($preferenceData['vaaz_center_id']);
                        if ($vaazCenter && $vaazCenter->event_id != $targetEventId) {
                            throw new \Exception('The selected Vaaz Center does not belong to the specified event for ITS ' . $preferenceData['its_id']);
                        }
                    }

                    if (isset($preferenceData['block_id'])) {
                        $block = Block::find($preferenceData['block_id']);
                        if (!$block || !$block->vaazCenter || $block->vaazCenter->event_id != $targetEventId) {
                            throw new \Exception('The selected Block does not belong to the specified event for ITS ' . $preferenceData['its_id']);
                        }
                        if (isset($preferenceData['vaaz_center_id']) && $block->vaaz_center_id != $preferenceData['vaaz_center_id']) {
                            throw new \Exception('The selected Block does not belong to the specified Vaaz Center for ITS ' . $preferenceData['its_id']);
                        }
                    }

                    if (isset($preferenceData['vaaz_center_id'])) {
                        $vaazCenter = VaazCenter::find($preferenceData['vaaz_center_id']);
                        if ($vaazCenter && $vaazCenter->est_capacity > 0) {
                            $currentPassesInCenter = PassPreference::where('vaaz_center_id', $vaazCenter->id)->count();
                            if ($currentPassesInCenter >= $vaazCenter->est_capacity) {
                                throw new \Exception('Selected Vaaz Center is full for ITS ' . $preferenceData['its_id']);
                            }
                        }
                    }

                    if (isset($preferenceData['block_id'])) {
                        $block = Block::find($preferenceData['block_id']);
                        if ($block && $block->capacity > 0) {
                            $currentPassesCount = PassPreference::where('block_id', $block->id)->count();
                            if ($currentPassesCount >= $block->capacity) {
                                throw new \Exception('Selected block is full for ITS ' . $preferenceData['its_id']);
                            }
                        }
                    }

                    $passPreference = PassPreference::create($preferenceData);
                    $createdPreferences[] = $passPreference->load(['block', 'vaazCenter']);
                }
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($createdPreferences, 201);
    }

    /**
     * @OA\Put(
     *      path="/api/pass-preferences",
     *      operationId="updatePassPreference",
     *      tags={"Pass Preferences"},
     *      summary="Update one or more existing pass preferences",
     *      description="Updates one or more existing pass preference records in a single transaction. Each object in the array must contain an 'its_id' to identify the record.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="An array of pass preference data objects to update.",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  required={"its_id"},
     *                  @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen whose preference is to be updated."),
     *                  @OA\Property(property="event_id", type="integer", nullable=true, description="ID of the event"),
     *                  @OA\Property(property="pass_type", type="string", enum={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}, nullable=true, description="Type of pass"),
     *                  @OA\Property(property="block_id", type="integer", nullable=true, description="ID of the block"),
     *                  @OA\Property(property="vaaz_center_id", type="integer", nullable=true, description="ID of the Vaaz center")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Pass preferences updated successfully",
     *          @OA\JsonContent(type="object", example={"message": "Pass preferences updated successfully."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Pass Preference not found for one or more records",
     *          @OA\JsonContent(type="object", example={"message": "Pass Preference not found for ITS: 12345678."})
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden. User is not authorized to update a preference for one or more ITS numbers.",
     *          @OA\JsonContent(type="object", example={"message": "Authorization failed for one or more ITS numbers."})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error or business logic error",
     *          @OA\JsonContent(type="object", example={"message": "Error for ITS 12345678: The selected block is full."})
     *      )
     * )
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $preferencesData = $request->all();

        if (!is_array($preferencesData) || empty($preferencesData) || !isset($preferencesData[0])) {
            return response()->json(['message' => 'Request body must be a non-empty array of pass preferences.'], 400);
        }

        $validator = Validator::make($preferencesData, [
            '*.its_id' => 'required|integer|exists:pass_preferences,its_id',
            '*.event_id' => 'sometimes|required|integer|exists:events,id',
            '*.pass_type' => ['sometimes', 'nullable', Rule::enum(PassType::class)],
            '*.block_id' => 'sometimes|nullable|integer|exists:blocks,id',
            '*.vaaz_center_id' => 'sometimes|nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedPreferences = $validator->validated();

        foreach ($validatedPreferences as $preferenceData) {
            if (!$this->isFamilyMember($request, $preferenceData['its_id'])) {
                return response()->json(['message' => 'Authorization failed for one or more ITS numbers.'], 403);
            }
        }

        try {
            DB::transaction(function () use ($validatedPreferences) {
                foreach ($validatedPreferences as $preferenceData) {
                    $passPreference = PassPreference::where('its_id', $preferenceData['its_id'])->lockForUpdate()->first();

                    if (!$passPreference) {
                        throw new \Exception("Pass Preference not found for ITS: {$preferenceData['its_id']}.", 404);
                    }

                    if ($passPreference->is_locked) {
                        throw new \Exception("Pass preference for ITS {$preferenceData['its_id']} is locked and cannot be updated.", 403);
                    }

                    $targetEventId = $preferenceData['event_id'] ?? $passPreference->event_id;

                    if (isset($preferenceData['vaaz_center_id'])) {
                        $vaazCenter = VaazCenter::find($preferenceData['vaaz_center_id']);
                        if ($vaazCenter && $vaazCenter->event_id != $targetEventId) {
                            throw new \Exception("Error for ITS {$preferenceData['its_id']}: The selected Vaaz Center does not belong to the target event.", 422);
                        }
                    }

                    if (isset($preferenceData['block_id'])) {
                        $block = Block::find($preferenceData['block_id']);
                        if ($block && (!$block->vaazCenter || $block->vaazCenter->event_id != $targetEventId)) {
                            throw new \Exception("Error for ITS {$preferenceData['its_id']}: The selected Block does not belong to the target event.", 422);
                        }
                    }

                    if (isset($preferenceData['vaaz_center_id']) && $passPreference->vaaz_center_id != $preferenceData['vaaz_center_id']) {
                        $newVaazCenter = VaazCenter::find($preferenceData['vaaz_center_id']);
                        if ($newVaazCenter && $newVaazCenter->est_capacity > 0) {
                            $count = PassPreference::where('vaaz_center_id', $newVaazCenter->id)->count();
                            if ($count >= $newVaazCenter->est_capacity) {
                                throw new \Exception("Error for ITS {$preferenceData['its_id']}: New selected Vaaz Center is full.", 422);
                            }
                        }
                    }

                    if (isset($preferenceData['block_id']) && $passPreference->block_id != $preferenceData['block_id']) {
                        $newBlock = Block::find($preferenceData['block_id']);
                        if ($newBlock && $newBlock->capacity > 0) {
                            $count = PassPreference::where('block_id', $newBlock->id)->count();
                            if ($count >= $newBlock->capacity) {
                                throw new \Exception("Error for ITS {$preferenceData['its_id']}: New selected block is full.", 422);
                            }
                        }
                    }

                    $passPreference->update($preferenceData);
                }
            });
        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 404 ? 404 : 422;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }

        return response()->json(['message' => 'Pass preferences updated successfully.']);
    }

    /**
     * @OA\Delete(
     *      path="/api/pass-preferences",
     *      operationId="deletePassPreference",
     *      tags={"Pass Preferences"},
     *      summary="Delete a pass preference",
     *      description="Deletes a pass preference record identified by its ITS ID. The ITS ID in the body is expected to be encrypted.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="query",
     *          description="Encrypted ITS ID of the mumineen whose preference is to be deleted.",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="event_id",
     *          in="query",
     *          description="ID of the event for which the preference is to be deleted.",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Pass preference deleted successfully",
     *          @OA\JsonContent(type="object", example={"message": "Pass Preference deleted successfully."})
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent(type="object", example={"message": "You are not authorized to delete this pass preference."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Pass Preference not found",
     *          @OA\JsonContent(type="object", example={"message": "Pass Preference not found for the given ITS number."})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(type="object", example={"its_id": {"The its id field is required."}})
     *      )
     * )
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|exists:pass_preferences,its_id,event_id,' . $request->input('event_id'), // Ensures its_id exists for the given event_id
            'event_id' => 'required|integer|exists:events,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $targetItsId = $request->input('its_id');
        $targetEventId = $request->input('event_id');

        // Authorization Check
        if (!$this->isFamilyMember($request, $targetItsId)) {
            return response()->json(['message' => 'You are not authorized to delete this pass preference.'], 403);
        }

        $passPreference = PassPreference::where('its_id', $targetItsId)
                                        ->where('event_id', $targetEventId)
                                        ->first();

        // Defensive check, though 'exists' rule should prevent this.
        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found for the given ITS number.'], 404);
        }

        $passPreference->delete();

        return response()->json(['message' => 'Pass Preference deleted successfully.']);
    }

    /**
     * @OA\Put(
     *      path="/api/pass-preferences/vaaz-center",
     *      operationId="updatePassPreferenceVaazCenter",
     *      tags={"Pass Preferences"},
     *      summary="Update the Vaaz Center for one or more pass preferences",
     *      description="Updates the Vaaz Center for multiple pass preferences in a single transaction. If a preference had a block assigned, and that block does not belong to the new Vaaz Center, the block assignment will be removed (set to null).",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="An array of objects, each with ITS ID, Event ID, and new Vaaz Center ID",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  required={"its_id", "event_id", "vaaz_center_id"},
     *                  @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen (decrypted by middleware)"),
     *                  @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *                  @OA\Property(property="vaaz_center_id", type="integer", description="ID of the new Vaaz center")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Vaaz Center updated successfully for all preferences",
     *          @OA\JsonContent(type="object", example={"message": "Pass preferences updated successfully."})
     *      ),
     *      @OA\Response(response=400, description="Invalid request body"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized to update one or more preferences"),
     *      @OA\Response(response=404, description="Pass Preference or Vaaz Center not found for one of the entries"),
     *      @OA\Response(response=422, description="Validation error or business logic error (e.g. Vaaz center not for this event, capacity full)")
     * )
     */
    public function updateVaazCenter(Request $request): JsonResponse
    {
        $preferencesData = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($preferencesData) || empty($preferencesData) || !isset($preferencesData[0])) {
            return response()->json([
                'error_code' => PassPreferenceErrorCode::INVALID_REQUEST_BODY->value,
                'message' => 'Request body must be a valid JSON array of preferences.',
                'details' => json_last_error_msg() !== 'No error' ? ['json_error' => json_last_error_msg()] : null
            ], 400);
        }

        $validator = Validator::make($preferencesData, [
            '*.its_id' => 'required|integer',
            '*.event_id' => 'required|integer|exists:events,id',
            '*.vaaz_center_id' => 'required|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error_code' => PassPreferenceErrorCode::VALIDATION_FAILED->value,
                'message' => 'The given data was invalid.',
                'details' => $validator->errors()->toArray()
            ], 422);
        }

        $validatedPreferences = $validator->validated();

        // Authorization check for all preferences upfront
        foreach ($validatedPreferences as $preferenceData) {
            if (!$this->isFamilyMember($request, $preferenceData['its_id'])) {
                return response()->json([
                    'error_code' => PassPreferenceErrorCode::AUTHORIZATION_FAILED->value,
                    'message' => 'Authorization failed for ITS ' . $preferenceData['its_id'] . '.',
                    'details' => ['its_id' => $preferenceData['its_id']]
                ], 403);
            }
        }

        try {
            DB::transaction(function () use ($validatedPreferences) {
                foreach ($validatedPreferences as $preferenceData) {
                    $itsId = $preferenceData['its_id'];
                    $eventId = $preferenceData['event_id'];
                    $newVaazCenterId = $preferenceData['vaaz_center_id'];

                    $passPreference = PassPreference::where('its_id', $itsId)
                                                    ->where('event_id', $eventId)
                                                    ->lockForUpdate()
                                                    ->first();

                    if (!$passPreference) {
                        // Use a distinct message format for easy parsing in catch block
                        throw new \Exception("PASS_PREFERENCE_NOT_FOUND_FOR_ITS_{$itsId}_EVENT_{$eventId}", 404);
                    }

                    if ($passPreference->is_locked) {
                        throw new \Exception("PASS_PREFERENCE_LOCKED_ITS_{$itsId}", 403);
                    }

                    $newVaazCenter = VaazCenter::find($newVaazCenterId);
                    if (!$newVaazCenter) {
                        throw new \Exception("VAAZ_CENTER_NOT_FOUND_{$newVaazCenterId}", 404);
                    }

                    if ($newVaazCenter->event_id != $eventId) {
                        throw new \Exception("VAAZ_CENTER_EVENT_MISMATCH_ITS_{$itsId}", 422);
                    }

                    // Only check capacity if moving to a *different* Vaaz Center
                    if ($passPreference->vaaz_center_id != $newVaazCenterId) {
                        // Fetch Mumineen to check gender
                        $mumineen = Mumineen::find($itsId);
                        if (!$mumineen) {
                            // This case should ideally not be hit if its_id in passPreference is valid and exists in mumineens table
                            throw new \Exception("MUMINEEN_NOT_FOUND_FOR_ITS_{$itsId}_EVENT_{$eventId}", 404);
                        }
                        $gender = isset($mumineen->gender) ? strtolower($mumineen->gender) : null;

                        $capacityFull = false;
                        $capacityMessage = "";

                        if ($gender === 'male') {
                            if ($newVaazCenter->male_capacity === null || $newVaazCenter->male_capacity == 0) {
                                $capacityFull = true;
                                $capacityMessage = "VAAZ_CENTER_NO_MALE_CAPACITY_ITS_{$itsId}_VC_{$newVaazCenterId}";
                            } else {
                                $currentMaleCount = PassPreference::join('mumineens', 'pass_preferences.its_id', '=', 'mumineens.its_id')
                                    ->where('pass_preferences.vaaz_center_id', $newVaazCenterId)
                                    ->where('pass_preferences.event_id', $eventId)
                                    ->whereRaw('LOWER(mumineens.gender) = ?', ['male'])
                                    ->count();
                                if ($currentMaleCount >= $newVaazCenter->male_capacity) {
                                    $capacityFull = true;
                                    $capacityMessage = "VAAZ_CENTER_MALE_FULL_ITS_{$itsId}_VC_{$newVaazCenterId}";
                                }
                            }
                        } elseif ($gender === 'female') {
                            if ($newVaazCenter->female_capacity === null || $newVaazCenter->female_capacity == 0) {
                                $capacityFull = true;
                                $capacityMessage = "VAAZ_CENTER_NO_FEMALE_CAPACITY_ITS_{$itsId}_VC_{$newVaazCenterId}";
                            } else {
                                $currentFemaleCount = PassPreference::join('mumineens', 'pass_preferences.its_id', '=', 'mumineens.its_id')
                                    ->where('pass_preferences.vaaz_center_id', $newVaazCenterId)
                                    ->where('pass_preferences.event_id', $eventId)
                                    ->whereRaw('LOWER(mumineens.gender) = ?', ['female'])
                                    ->count();
                                if ($currentFemaleCount >= $newVaazCenter->female_capacity) {
                                    $capacityFull = true;
                                    $capacityMessage = "VAAZ_CENTER_FEMALE_FULL_ITS_{$itsId}_VC_{$newVaazCenterId}";
                                }
                            }
                        } else { // Gender is not 'male' or 'female' (includes null, empty, or other genders)
                            $capacityFull = true;
                            $genderDisplay = $gender ?: 'unspecified';
                            $capacityMessage = "VAAZ_CENTER_UNSUPPORTED_GENDER_ITS_{$itsId}_GENDER_{$genderDisplay}_VC_{$newVaazCenterId}";
                        }

                        if ($capacityFull) {
                            throw new \Exception($capacityMessage, 422);
                        }
                    }

                    // If moving to a new Vaaz center, and previously assigned to a block,
                    // nullify block_id if the block belongs to a different Vaaz center than the new one.
                    if ($passPreference->block_id && $passPreference->vaaz_center_id != $newVaazCenterId) {
                        $block = Block::find($passPreference->block_id);
                        if ($block && $block->vaaz_center_id != $newVaazCenterId) {
                            $passPreference->block_id = null;
                        }
                    }

                    $passPreference->vaaz_center_id = $newVaazCenterId;
                    $passPreference->save();
                }
            });
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $errorCode = PassPreferenceErrorCode::UNKNOWN_ERROR;
            $details = ['original_message' => $message];
            $userMessage = 'An unexpected error occurred while updating Vaaz Center preferences.';
            $statusCode = $e->getCode() == 404 ? 404 : ($e->getCode() == 422 ? 422 : 500); // Default to 422 for business logic, 404 for not found, 500 for others

            if (preg_match('/PASS_PREFERENCE_NOT_FOUND_FOR_ITS_(\d+)_EVENT_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::RESOURCE_NOT_FOUND;
                $userMessage = "Pass Preference not found for ITS {$matches[1]} and Event ID {$matches[2]}.";
                $details = ['its_id' => $matches[1], 'event_id' => $matches[2], 'resource_type' => 'PassPreference'];
                $statusCode = 404;
            } elseif (preg_match('/PASS_PREFERENCE_LOCKED_ITS_(\\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::AUTHORIZATION_FAILED;
                $userMessage = "Pass preference for ITS {$matches[1]} is locked and cannot be updated.";
                $details = ['its_id' => $matches[1]];
                $statusCode = 403;
            } elseif (preg_match('/MUMINEEN_NOT_FOUND_FOR_ITS_(\d+)_EVENT_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::RESOURCE_NOT_FOUND;
                $userMessage = "Mumineen record not found for ITS {$matches[1]} to perform capacity check for event {$matches[2]}.";
                $details = ['its_id' => $matches[1], 'event_id' => $matches[2], 'resource_type' => 'Mumineen'];
                $statusCode = 404;
            } elseif (preg_match('/VAAZ_CENTER_NOT_FOUND_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::RESOURCE_NOT_FOUND;
                $userMessage = "Target Vaaz Center with ID {$matches[1]} not found.";
                $details = ['vaaz_center_id' => $matches[1], 'resource_type' => 'VaazCenter'];
                $statusCode = 404;
            } elseif (preg_match('/VAAZ_CENTER_EVENT_MISMATCH_ITS_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::VAAZ_CENTER_EVENT_MISMATCH;
                $userMessage = "The selected Vaaz Center for ITS {$matches[1]} does not belong to the specified event.";
                $details = ['its_id' => $matches[1]];
                $statusCode = 422;
            } elseif (preg_match('/VAAZ_CENTER_NO_MALE_CAPACITY_ITS_(\d+)_VC_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::VAAZ_CENTER_CAPACITY_GENDER_UNAVAILABLE;
                $userMessage = "The selected Vaaz Center ID {$matches[2]} for ITS {$matches[1]} has no defined capacity for males.";
                $details = ['its_id' => $matches[1], 'vaaz_center_id' => $matches[2], 'gender' => 'male'];
                $statusCode = 422;
            } elseif (preg_match('/VAAZ_CENTER_MALE_FULL_ITS_(\d+)_VC_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::VAAZ_CENTER_FULL;
                $userMessage = "The selected Vaaz Center ID {$matches[2]} for ITS {$matches[1]} is full for males.";
                $details = ['its_id' => $matches[1], 'vaaz_center_id' => $matches[2], 'gender' => 'male'];
                $statusCode = 422;
            } elseif (preg_match('/VAAZ_CENTER_NO_FEMALE_CAPACITY_ITS_(\d+)_VC_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::VAAZ_CENTER_CAPACITY_GENDER_UNAVAILABLE;
                $userMessage = "The selected Vaaz Center ID {$matches[2]} for ITS {$matches[1]} has no defined capacity for females.";
                $details = ['its_id' => $matches[1], 'vaaz_center_id' => $matches[2], 'gender' => 'female'];
                $statusCode = 422;
            } elseif (preg_match('/VAAZ_CENTER_FEMALE_FULL_ITS_(\d+)_VC_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::VAAZ_CENTER_FULL;
                $userMessage = "The selected Vaaz Center ID {$matches[2]} for ITS {$matches[1]} is full for females.";
                $details = ['its_id' => $matches[1], 'vaaz_center_id' => $matches[2], 'gender' => 'female'];
                $statusCode = 422;
            } elseif (preg_match('/VAAZ_CENTER_UNSUPPORTED_GENDER_ITS_(\d+)_GENDER_(.+)_VC_(\d+)/', $message, $matches)) {
                $errorCode = PassPreferenceErrorCode::VAAZ_CENTER_CAPACITY_GENDER_UNSUPPORTED;
                $userMessage = "The selected Vaaz Center ID {$matches[3]} for ITS {$matches[1]} does not support preferences for gender '{$matches[2]}'.";
                $details = ['its_id' => $matches[1], 'gender' => $matches[2], 'vaaz_center_id' => $matches[3]];
                $statusCode = 422;
            } elseif (preg_match('/VAAZ_CENTER_FULL_ITS_(\d+)/', $message, $matches)) { // Generic fallback, should be less common now
                $errorCode = PassPreferenceErrorCode::VAAZ_CENTER_FULL;
                $userMessage = "The selected Vaaz Center for ITS {$matches[1]} is full (general capacity). Contact support if gender specific capacity should apply.";
                $details = ['its_id' => $matches[1]];
                $statusCode = 422;
            }

            return response()->json([
                'error_code' => $errorCode->value,
                'message' => $userMessage,
                'details' => $details
            ], $statusCode);
        }

        return response()->json(['message' => 'Pass preferences updated successfully.']);
    }

    /**
     * @OA\Put(
     *      path="/api/pass-preferences/pass-type",
     *      operationId="updatePassPreferencePassType",
     *      tags={"Pass Preferences"},
     *      summary="Update the Pass Type for a specific pass preference",
     *      description="Updates the Pass Type for a pass preference identified by ITS ID and Event ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="ITS ID, Event ID, and new Pass Type",
     *          @OA\JsonContent(
     *              required={"its_id", "event_id", "pass_type"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen (decrypted by middleware)"),
     *              @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *              @OA\Property(property="pass_type", type="string", enum={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}, description="New type of pass")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Pass Type updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/PassPreference")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized"),
     *      @OA\Response(response=404, description="Pass Preference not found"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updatePassType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer', // Middleware should have decrypted this
            'event_id' => 'required|integer|exists:events,id',
            'pass_type' => ['required', Rule::enum(PassType::class)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $itsId = $validatedData['its_id'];
        $eventId = $validatedData['event_id'];

        if (!$this->isFamilyMember($request, $itsId)) {
            return response()->json(['message' => 'You are not authorized to update this pass preference.'], 403);
        }

        $passPreference = PassPreference::where('its_id', $itsId)
                                        ->where('event_id', $eventId)
                                        ->first();

        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found for the given ITS ID and Event ID.'], 404);
        }

        $passPreference->pass_type = $validatedData['pass_type'];
        $passPreference->save();

        return response()->json($passPreference->load(['block', 'vaazCenter', 'event']));
    }

    /**
     * @OA\Post(
     *      path="/api/pass-preferences/vaaz-center",
     *      operationId="storePassPreferenceVaazCenter",
     *      tags={"Pass Preferences"},
     *      summary="Create one or more new pass preferences with a specific Vaaz Center",
     *      description="Creates one or more new pass preference records in a single transaction, assigning a Vaaz Center. Each ITS ID must be unique for the given Event ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="An array of objects, each with ITS ID, Event ID, and Vaaz Center ID for the new preference",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  required={"its_id", "event_id", "vaaz_center_id"},
     *                  @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen (decrypted by middleware)"),
     *                  @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *                  @OA\Property(property="vaaz_center_id", type="integer", description="ID of the Vaaz center")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Pass preferences created successfully",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/PassPreference")
     *          )
     *      ),
     *      @OA\Response(response=400, description="Invalid request body"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized for one or more ITS numbers"),
     *      @OA\Response(response=404, description="Vaaz Center not found"),
     *      @OA\Response(response=422, description="Validation error (e.g., already exists, Vaaz center not for this event, capacity full)")
     * )
     */
    public function storeVaazCenterPreference(Request $request): JsonResponse
    {
        $preferencesData = json_decode($request->getContent(), true);

        if (!is_array($preferencesData) || empty($preferencesData) || !isset($preferencesData[0])) {
            return response()->json(['message' => 'Request body must be a non-empty array of preferences.'], 400);
        }

        $validator = Validator::make($preferencesData, [
            '*.its_id' => 'required|integer|distinct',
            '*.event_id' => 'required|integer|exists:events,id',
            '*.vaaz_center_id' => 'required|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedPreferences = $validator->validated();
        $createdPreferences = [];

        foreach ($validatedPreferences as $preferenceData) {
            if (!$this->isFamilyMember($request, $preferenceData['its_id'])) {
                return response()->json(['message' => 'Authorization failed for one or more ITS numbers.'], 403);
            }
        }

        try {
            DB::transaction(function () use ($validatedPreferences, &$createdPreferences, $request) {
                foreach ($validatedPreferences as $preferenceData) {
                    $itsId = $preferenceData['its_id'];
                    $eventId = $preferenceData['event_id'];
                    $vaazCenterId = $preferenceData['vaaz_center_id'];

                    // Check for uniqueness within the transaction
                    $existing = PassPreference::where('its_id', $itsId)
                                                ->where('event_id', $eventId)
                                                ->lockForUpdate()
                                                ->first();
                    if ($existing) {
                        if ($existing->is_locked) {
                            throw new \Exception("Pass preference for ITS {$itsId} is locked and cannot be changed.");
                        }
                        throw new \Exception("Pass preference for ITS {$itsId} and Event {$eventId} already exists.");
                    }

                    // Fetch Mumineen to check gender
                    $mumineen = Mumineen::find($itsId);
                    if (!$mumineen) {
                        throw new \Exception("Mumineen with ITS ID {$itsId} not found.");
                    }
                    $gender = isset($mumineen->gender) ? strtolower($mumineen->gender) : null;

                    // Fetch Vaaz Center
                    $vaazCenter = VaazCenter::find($vaazCenterId);
                    if (!$vaazCenter) {
                        throw new \Exception("Vaaz Center with ID {$vaazCenterId} not found.");
                    }

                    // Check if Vaaz Center is assigned to the correct event
                    if ($vaazCenter->event_id !== null && $vaazCenter->event_id != $eventId) {
                        throw new \Exception("Vaaz Center ID {$vaazCenterId} is not assigned to Event ID {$eventId}.");
                    }

                    $capacityFull = false;
                    $capacityMessage = "";

                    if ($gender === 'male') {
                        if ($vaazCenter->male_capacity === null || $vaazCenter->male_capacity == 0) {
                            $capacityFull = true;
                            $capacityMessage = "Vaaz Center ID {$vaazCenterId} has no defined or available male capacity for Event ID {$eventId}.";
                        } else {
                            $currentMaleCount = PassPreference::join('mumineens', 'pass_preferences.its_id', '=', 'mumineens.its_id')
                                ->where('pass_preferences.vaaz_center_id', $vaazCenterId)
                                ->where('pass_preferences.event_id', $eventId)
                                ->whereRaw('LOWER(mumineens.gender) = ?', ['male'])
                                ->count();
                            if ($currentMaleCount >= $vaazCenter->male_capacity) {
                                $capacityFull = true;
                                $capacityMessage = "Vaaz Center ID {$vaazCenterId} is full for males for Event ID {$eventId}.";
                            }
                        }
                    } elseif ($gender === 'female') {
                        if ($vaazCenter->female_capacity === null || $vaazCenter->female_capacity == 0) {
                            $capacityFull = true;
                            $capacityMessage = "Vaaz Center ID {$vaazCenterId} has no defined or available female capacity for Event ID {$eventId}.";
                        } else {
                            $currentFemaleCount = PassPreference::join('mumineens', 'pass_preferences.its_id', '=', 'mumineens.its_id')
                                ->where('pass_preferences.vaaz_center_id', $vaazCenterId)
                                ->where('pass_preferences.event_id', $eventId)
                                ->whereRaw('LOWER(mumineens.gender) = ?', ['female'])
                                ->count();
                            if ($currentFemaleCount >= $vaazCenter->female_capacity) {
                                $capacityFull = true;
                                $capacityMessage = "Vaaz Center ID {$vaazCenterId} is full for females for Event ID {$eventId}.";
                            }
                        }
                    } else { // Gender is not 'male' or 'female' (includes null, empty, or other genders)
                        $capacityFull = true;
                        $genderDisplay = $gender ?: 'unspecified';
                        $capacityMessage = "Vaaz Center ID {$vaazCenterId} does not support pass preference for '{$genderDisplay}' gender, or gender is not specified.";
                    }

                    if ($capacityFull) {
                        throw new \Exception($capacityMessage);
                    }

                    $passPreference = PassPreference::create([
                        'its_id' => $itsId,
                        'event_id' => $eventId,
                        'vaaz_center_id' => $vaazCenterId,
                    ]);
                    $createdPreferences[] = $passPreference->load(['vaazCenter', 'event']);
                }
            });
        } catch (\Exception $e) {
            // Ensure getCode() returns an int for HTTP status codes
            $exceptionCode = $e->getCode();
            $statusCode = (is_int($exceptionCode) && in_array($exceptionCode, [400, 401, 403, 404])) ? $exceptionCode : 422;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }


        return response()->json($createdPreferences, 201);
    }

    /**
     * @OA\Post(
     *      path="/api/pass-preferences/pass-type",
     *      operationId="storePassPreferencePassType",
     *      tags={"Pass Preferences"},
     *      summary="Create a new pass preference with a specific Pass Type",
     *      description="Creates a new pass preference record identified by ITS ID and Event ID, assigning a Pass Type. ITS ID must be unique for the given Event ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="ITS ID, Event ID, and Pass Type for the new preference",
     *          @OA\JsonContent(
     *              required={"its_id", "event_id", "pass_type"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen (decrypted by middleware)"),
     *              @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *              @OA\Property(property="pass_type", type="string", enum={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}, description="Type of pass")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Pass preference created successfully",
     *          @OA\JsonContent(ref="#/components/schemas/PassPreference")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized"),
     *      @OA\Response(response=422, description="Validation error (e.g., already exists)")
     * )
     */
    public function storePassTypePreference(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its_id' => [
                'required',
                'integer',
                Rule::unique('pass_preferences')->where(function ($query) use ($request) {
                    return $query->where('event_id', $request->input('event_id'));
                }),
            ],
            'event_id' => 'required|integer|exists:events,id',
            'pass_type' => ['required', Rule::enum(PassType::class)],
        ], [
            'its_id.unique' => 'A pass preference already exists for this ITS ID and Event ID combination.',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $itsId = $validatedData['its_id'];

        if (!$this->isFamilyMember($request, $itsId)) {
            return response()->json(['message' => 'You are not authorized to create this pass preference.'], 403);
        }

        $passPreference = PassPreference::create([
            'its_id' => $itsId,
            'event_id' => $validatedData['event_id'],
            'pass_type' => $validatedData['pass_type'],
            // 'vaaz_center_id' and 'block_id' will be null by default or can be set later
        ]);

        return response()->json($passPreference->load(['block', 'vaazCenter', 'event']), 201);
    }

    /**
     * @OA\Get(
     *      path="/api/pass-types",

     *      operationId="getAvailablePassTypes",
     *      tags={"Pass Preferences"},
     *      summary="Get all available pass types",
     *      description="Returns a list of all available pass types defined in the PassType enum.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(type="string", example="RAHAT")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getAvailablePassTypes(): JsonResponse
    {
        return response()->json(array_column(PassType::cases(), 'value'));
    }

    /**
     * @OA\Put(
     *      path="/api/pass-preferences/lock-preferences",
     *      operationId="bulkUpdateLockStatus",
     *      tags={"Pass Preferences"},
     *      summary="Bulk update the lock status of pass preferences",
     *      description="Updates the `is_locked` status for multiple pass preferences in a single transaction.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="An object containing an array of ITS IDs and the new lock status.",
     *          @OA\JsonContent(
     *              required={"its_id", "is_locked"},
     *              @OA\Property(property="its_id", type="array", @OA\Items(type="string"), description="An array of ITS IDs to update."),
     *              @OA\Property(property="is_locked", type="boolean", description="The new lock status to apply to all specified ITS IDs.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Lock status updated successfully",
     *          @OA\JsonContent(type="object", example={"message": "Lock status updated for X records."})
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkUpdateLockStatus(Request $request): JsonResponse
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'its_id' => 'required|array',
            'its_id.*' => 'string|exists:pass_preferences,its_id',
            'is_locked' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $itsIds = $validatedData['its_id'];
        $isLocked = $validatedData['is_locked'];
        $updatedCount = 0;

        DB::transaction(function () use ($itsIds, $isLocked, &$updatedCount) {
            $updatedCount = PassPreference::whereIn('its_id', $itsIds)
                ->update(['is_locked' => $isLocked]);
        });

        return response()->json(['message' => "Lock status updated for {$updatedCount} records."]);
    }



    /**
     * @OA\Put(
     *      path="/api/pass-preferences/bulk-assign-vaaz-center",
     *      operationId="bulkAssignVaazCenter",
     *      tags={"Pass Preferences"},
     *      summary="Bulk assign multiple Mumineen to a Vaaz Center",
     *      description="Assigns a list of Mumineen (by ITS ID) to a specified Vaaz Center for a given event. This is an admin-only endpoint that checks for gender-specific capacity.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Object containing the event ID, Vaaz Center ID, an array of ITS IDs, and the gender.",
     *          @OA\JsonContent(
     *              required={"event_id", "vaaz_center_id", "its_ids", "gender"},
     *              @OA\Property(property="event_id", type="integer", description="The ID of the event."),
     *              @OA\Property(property="vaaz_center_id", type="integer", description="The ID of the Vaaz Center to assign."),
     *              @OA\Property(property="its_ids", type="array", @OA\Items(type="string"), description="An array of Mumineen ITS IDs to assign."),
     *              @OA\Property(property="gender", type="string", enum={"male", "female"}, description="The gender to assign, used for capacity checking.")
     *          )
     *      ),
     *      @OA\Response(response=200, description="Assignment successful"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden (Admin access required)"),
     *      @OA\Response(response=422, description="Validation error, capacity exceeded, or gender mismatch")
     * )
     */
    public function bulkAssignVaazCenter(Request $request): JsonResponse
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
            'vaaz_center_id' => 'required|integer|exists:vaaz_centers,id',
            'its_ids' => 'required|array',
            'its_ids.*' => 'required|string|exists:pass_preferences,its_id',
            'gender' => ['required', Rule::in(array_column(Gender::cases(), 'value'))],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $eventId = $validatedData['event_id'];
        $vaazCenterId = $validatedData['vaaz_center_id'];
        $itsIds = array_unique($validatedData['its_ids']);
        $gender = $validatedData['gender'];

        // Verify that all provided ITS IDs match the specified gender
        $mumineenWithMatchingGenderCount = Mumineen::whereIn('its_id', $itsIds)->where('gender', $gender)->count();
        if ($mumineenWithMatchingGenderCount !== count($itsIds)) {
            return response()->json(['message' => 'One or more provided ITS IDs do not match the specified gender or do not exist.'], 422);
        }

        $updatedCount = 0;

        try {
            DB::transaction(function () use ($eventId, $vaazCenterId, $itsIds, $gender, &$updatedCount) {
                $vaazCenter = VaazCenter::lockForUpdate()->find($vaazCenterId);
                if (!$vaazCenter) {
                    // This should ideally be caught by validation, but as a safeguard:
                    throw new \Exception('Vaaz Center not found.', 404);
                }

                $capacity = ($gender === Gender::MALE->value) ? $vaazCenter->male_capacity : $vaazCenter->female_capacity;

                // Capacity Check
                $currentOccupancy = PassPreference::where('event_id', $eventId)
                    ->where('vaaz_center_id', $vaazCenterId)
                    ->whereHas('mumineen', function ($query) use ($gender) {
                        $query->where('gender', $gender);
                    })
                    ->count();
                
                $newAssignmentsCount = PassPreference::whereIn('its_id', $itsIds)
                    ->where('event_id', $eventId)
                    ->where(function ($query) use ($vaazCenterId) {
                        $query->where('vaaz_center_id', '!=', $vaazCenterId)->orWhereNull('vaaz_center_id');
                    })
                    ->count();

                if (($currentOccupancy + $newAssignmentsCount) > $capacity) {
                    throw new \Exception(json_encode([
                        'message' => 'Assigning these Mumineen would exceed the Vaaz Center capacity for the specified gender.',
                        'gender' => $gender,
                        'capacity' => $capacity,
                        'current_occupancy' => $currentOccupancy,
                        'new_assignments' => $newAssignmentsCount,
                    ]), 422);
                }

                $updatedCount = PassPreference::whereIn('its_id', $itsIds)
                    ->where('event_id', $eventId)
                    ->update(['vaaz_center_id' => $vaazCenterId]);
            });

            return response()->json(['message' => "Successfully assigned {$updatedCount} Mumineen to the Vaaz Center."]);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() == 422 || $e->getCode() == 404 ? $e->getCode() : 500;
            $message = $e->getCode() == 422 ? json_decode($e->getMessage(), true) : ['message' => $e->getMessage()];
            if ($statusCode === 500) {
                 // Log internal server errors for debugging
                 Log::error('Bulk assign Vaaz Center error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                 $message = ['message' => 'An internal server error occurred.'];
            }
            return response()->json($message, $statusCode);
        }
    }

    /**
     * Check if a given ITS ID belongs to the authenticated user's family.
     *
     * @param int $targetItsId The ITS ID to check.
     * @return bool True if the target is in the same family, false otherwise.
     */
    private function isFamilyMember($request, $targetItsId): bool
    {
        $its_id = $request->input('user_decrypted_its_id');

        // If there's no authenticated user or they don't have an ITS ID, deny access.
        if (!$its_id || !isset($its_id)) {
            error_log('No authenticated user or ITS ID not found.');
            return false;
        }

        // Get HOF ID for both the current user and the target user.
        $currentUserRecord = Mumineen::where('its_id', $its_id)->first(['hof_id']);
        $targetUserRecord = Mumineen::where('its_id', $targetItsId)->first(['hof_id']);

        // If either record doesn't exist, or their hof_id is null, they can't be in the same family.
        if (!$currentUserRecord || !$targetUserRecord || is_null($currentUserRecord->hof_id) || is_null($targetUserRecord->hof_id)) {
            return false;
        }

        // They are in the same family if their HOF ID is the same and not null.
        return $currentUserRecord->hof_id === $targetUserRecord->hof_id;
    }

    /**
     * Get the authenticated user's ITS ID and their family members' ITS IDs.
     * @param \App\Models\User $user The authenticated user.
     * @return array An array of ITS IDs.
     */
    private function getFamilyItsIds($targetItsId)
    {
        // Fallback: Query Mumineen table if family_its_ids is not readily available from token/user object
        // This requires the User model to have an 'its_id' attribute and Mumineen model setup.
        $loggedInUserItsId = $targetItsId; // Assuming User model has its_id attribute
        $familyItsIds = [$loggedInUserItsId];

        $mumineenUser = Mumineen::where('its_id', $loggedInUserItsId)->first();

        if ($mumineenUser && $mumineenUser->hof_id) {
            // Get all family members including HoF
            $members = Mumineen::where('hof_id', $mumineenUser->hof_id)->pluck('its_id')->toArray();
            $familyItsIds = array_merge($familyItsIds, $members);
        }
        // else, it's just the user themselves, already added.
        return array_unique($familyItsIds);
    }
}
