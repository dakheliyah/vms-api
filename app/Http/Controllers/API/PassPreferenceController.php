<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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

        $vaazCenters = $vaazCentersQuery->withCount('passPreferences') // Count passes directly associated with the VaazCenter
            ->get();

        if ($vaazCenterId && $vaazCenters->isEmpty()) {
            return response()->json(['message' => 'The specified Vaaz Center was not found for the given event.'], 404);
        }

        $summary = $vaazCenters->map(function ($vaazCenter) {
            $vaazCenterIssuedPasses = $vaazCenter->pass_preferences_count ?? 0;
            $vaazCenterCapacity = $vaazCenter->est_capacity ?? 0;
            $vaazCenterAvailability = ($vaazCenterCapacity > 0) ? ($vaazCenterCapacity - $vaazCenterIssuedPasses) : 'unlimited';

            return [
                'id' => $vaazCenter->id,
                'name' => $vaazCenter->name,
                'vaaz_center_capacity' => $vaazCenterCapacity,
                'vaaz_center_issued_passes' => $vaazCenterIssuedPasses,
                'vaaz_center_availability' => $vaazCenterAvailability,
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
        if ($request->has('its_id')) {
            $targetItsId = $request->input('its_id');

            // Authorization Check: Ensure the requested ITS ID is in the user's family.
            if (!$this->isFamilyMember($targetItsId)) {
                return response()->json(['message' => 'You are not authorized to view this pass preference.'], 403);
            }

            $passPreference = PassPreference::where('its_id', $targetItsId)->with(['block', 'vaazCenter', 'event'])->first();
            if (!$passPreference) {
                return response()->json(['message' => 'Pass Preference not found for this ITS number'], 404);
            }
            return response()->json($passPreference);
        }

        // Listing all preferences is a potential data leak and should be restricted.
        // Returning an empty array for non-specific requests.
        // Implement role-based access control to allow admins to see all.
        return response()->json([]);
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
            '*.its_id' => 'required|integer|distinct|unique:pass_preferences,its_id',
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
            if (!$this->isFamilyMember($preferenceData['its_id'])) {
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
            if (!$this->isFamilyMember($preferenceData['its_id'])) {
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
     *      @OA\RequestBody(
     *          required=true,
     *          description="ITS ID of the pass preference to delete.",
     *          @OA\JsonContent(
     *              required={"its_id"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen whose preference is to be deleted.")
     *          )
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
            'its_id' => 'required|integer|exists:pass_preferences,its_id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $targetItsId = $request->input('its_id');

        // Authorization Check
        if (!$this->isFamilyMember($targetItsId)) {
            return response()->json(['message' => 'You are not authorized to delete this pass preference.'], 403);
        }

        $passPreference = PassPreference::where('its_id', $targetItsId)->first();

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
     *      summary="Update the Vaaz Center for a specific pass preference",
     *      description="Updates the Vaaz Center for a pass preference identified by ITS ID and Event ID. If the preference had a block assigned, and that block does not belong to the new Vaaz Center, the block assignment will be removed (set to null).",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="ITS ID, Event ID, and new Vaaz Center ID",
     *          @OA\JsonContent(
     *              required={"its_id", "event_id", "vaaz_center_id"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen (decrypted by middleware)"),
     *              @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *              @OA\Property(property="vaaz_center_id", type="integer", description="ID of the new Vaaz center")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Vaaz Center updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/PassPreference")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized to update this preference"),
     *      @OA\Response(response=404, description="Pass Preference or Vaaz Center not found"),
     *      @OA\Response(response=422, description="Validation error or business logic error (e.g. Vaaz center not for this event, capacity full)")
     * )
     */
    public function updateVaazCenter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer', // Middleware should have decrypted this
            'event_id' => 'required|integer|exists:events,id',
            'vaaz_center_id' => 'required|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $itsId = $validatedData['its_id'];
        $eventId = $validatedData['event_id'];
        $newVaazCenterId = $validatedData['vaaz_center_id'];

        if (!$this->isFamilyMember($itsId)) {
            return response()->json(['message' => 'You are not authorized to update this pass preference.'], 403);
        }

        $passPreference = PassPreference::where('its_id', $itsId)
                                        ->where('event_id', $eventId)
                                        ->first();

        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found for the given ITS ID and Event ID.'], 404);
        }

        $newVaazCenter = VaazCenter::find($newVaazCenterId);
        if (!$newVaazCenter) { 
            return response()->json(['message' => 'Target Vaaz Center not found.'], 404);
        }

        if ($newVaazCenter->event_id != $eventId) {
            return response()->json(['message' => 'The selected Vaaz Center does not belong to the specified event.'], 422);
        }

        if ($newVaazCenter->est_capacity > 0) {
            $currentPassesInNewCenter = PassPreference::where('vaaz_center_id', $newVaazCenterId)
                                                ->when($passPreference->vaaz_center_id == $newVaazCenterId, function ($query) use ($passPreference) {
                                                    return $query->where('id', '!=', $passPreference->id);
                                                })
                                                ->count();
            if ($currentPassesInNewCenter >= $newVaazCenter->est_capacity) {
                return response()->json(['message' => 'The selected new Vaaz Center is full.'], 422);
            }
        }

        if ($passPreference->block_id) {
            $block = Block::find($passPreference->block_id);
            if ($block && $block->vaaz_center_id != $newVaazCenterId) {
                $passPreference->block_id = null;
            }
        }

        $passPreference->vaaz_center_id = $newVaazCenterId;
        $passPreference->save();

        return response()->json($passPreference->load(['block', 'vaazCenter', 'event']));
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

        if (!$this->isFamilyMember($itsId)) {
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
     *      summary="Create a new pass preference with a specific Vaaz Center",
     *      description="Creates a new pass preference record identified by ITS ID and Event ID, assigning a Vaaz Center. ITS ID must be unique for the given Event ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="ITS ID, Event ID, and Vaaz Center ID for the new preference",
     *          @OA\JsonContent(
     *              required={"its_id", "event_id", "vaaz_center_id"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen (decrypted by middleware)"),
     *              @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *              @OA\Property(property="vaaz_center_id", type="integer", description="ID of the Vaaz center")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Pass preference created successfully",
     *          @OA\JsonContent(ref="#/components/schemas/PassPreference")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized"),
     *      @OA\Response(response=404, description="Vaaz Center not found"),
     *      @OA\Response(response=422, description="Validation error (e.g., already exists, Vaaz center not for this event, capacity full)")
     * )
     */
    public function storeVaazCenterPreference(Request $request): JsonResponse
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
            'vaaz_center_id' => 'required|integer|exists:vaaz_centers,id',
        ], [
            'its_id.unique' => 'A pass preference already exists for this ITS ID and Event ID combination.',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $itsId = $validatedData['its_id'];
        $eventId = $validatedData['event_id'];
        $vaazCenterId = $validatedData['vaaz_center_id'];

        if (!$this->isFamilyMember($itsId)) {
            return response()->json(['message' => 'You are not authorized to create this pass preference.'], 403);
        }

        $vaazCenter = VaazCenter::find($vaazCenterId);
        if (!$vaazCenter) {
             // Should be caught by 'exists' validation, but as a safeguard.
            return response()->json(['message' => 'Target Vaaz Center not found.'], 404);
        }

        if ($vaazCenter->event_id != $eventId) {
            return response()->json(['message' => 'The selected Vaaz Center does not belong to the specified event.'], 422);
        }

        if ($vaazCenter->est_capacity > 0) {
            $currentPassesInCenter = PassPreference::where('vaaz_center_id', $vaazCenterId)->count();
            if ($currentPassesInCenter >= $vaazCenter->est_capacity) {
                return response()->json(['message' => 'The selected Vaaz Center is full.'], 422);
            }
        }

        $passPreference = PassPreference::create([
            'its_id' => $itsId,
            'event_id' => $eventId,
            'vaaz_center_id' => $vaazCenterId,
            // 'pass_type' and 'block_id' will be null by default or can be set later
        ]);

        return response()->json($passPreference->load(['block', 'vaazCenter', 'event']), 201);
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

        if (!$this->isFamilyMember($itsId)) {
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
     * Check if a given ITS ID belongs to the authenticated user's family.
     *
     * @param int $targetItsId The ITS ID to check.
     * @return bool True if the target is in the same family, false otherwise.
     */
    private function isFamilyMember($targetItsId): bool
    {
        $currentUser = auth()->user();

        // If there's no authenticated user or they don't have an ITS ID, deny access.
        if (!$currentUser || !isset($currentUser->its_id)) {
            return false;
        }

        $currentUserItsId = $currentUser->its_id;

        // Get HOF ID for both the current user and the target user.
        $currentUserRecord = Mumineen::where('id', $currentUserItsId)->first(['hof_id']);
        $targetUserRecord = Mumineen::where('id', $targetItsId)->first(['hof_id']);

        // If either record doesn't exist, or their hof_id is null, they can't be in the same family.
        if (!$currentUserRecord || !$targetUserRecord || is_null($currentUserRecord->hof_id) || is_null($targetUserRecord->hof_id)) {
            return false;
        }

        // They are in the same family if their HOF ID is the same and not null.
        return $currentUserRecord->hof_id === $targetUserRecord->hof_id;
    }
}
