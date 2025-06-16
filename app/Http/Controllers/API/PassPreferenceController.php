<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PassPreference;
use App\Models\VaazCenter;
use App\Models\Block;
use App\Models\Event; // Added Event model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Enums\PassType;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

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
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(type="object", example={"event_id": {"The event id field is required."}})
     *      )
     * )
     * Provide a summary of pass availability for a given event.
     */
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $vaazCenters = VaazCenter::where('event_id', $request->input('event_id'))
            ->withCount('passPreferences') // Count passes directly associated with the VaazCenter
            ->with(['blocks' => function ($query) {
                $query->withCount('passPreferences'); // Count passes for each block
            }])
            ->get();

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
     *      path="/api/pass-preferences",
     *      operationId="getPassPreferences",
     *      tags={"Pass Preferences"},
     *      summary="List all pass preferences or get a specific one by ITS ID",
     *      description="Returns a list of all pass preferences or a single pass preference if an ITS ID is provided. The ITS ID is expected to be encrypted and will be decrypted by middleware.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="query",
     *          description="Encrypted ITS ID of the mumineen to retrieve a specific pass preference. If not provided, lists all preferences.",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              description="Can be a single PassPreference object or an array of PassPreference objects.",
     *              example={{"id": 1, "its_id": 12345, "event_id": 1, "pass_type": "RAHAT", "block_id": 1, "vaaz_center_id": 1, "created_at": "2023-01-01T00:00:00.000000Z", "updated_at": "2023-01-01T00:00:00.000000Z", "block": {"id": 1, "name": "Block A"}, "vaazCenter": {"id": 1, "name": "Center 1"}, "event": {"id": 1, "name": "Event Name"}}} 
     *          )
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
            $passPreference = PassPreference::where('its_id', $request->input('its_id'))->with(['block', 'vaazCenter', 'event'])->first(); 
            if (!$passPreference) {
                return response()->json(['message' => 'Pass Preference not found for this ITS number'], 404);
            }
            return response()->json($passPreference);
        }

        // Consider pagination for `all()` if the list can grow large
        return PassPreference::with(['block', 'vaazCenter', 'event'])->get(); 
    }

    /**
     * @OA\Post(
     *      path="/api/pass-preferences",
     *      operationId="storePassPreference",
     *      tags={"Pass Preferences"},
     *      summary="Create a new pass preference",
     *      description="Creates a new pass preference record. The ITS ID is expected to be encrypted.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Pass preference data",
     *          @OA\JsonContent(
     *              required={"its_id", "event_id"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen. Unique for pass_preferences."),
     *              @OA\Property(property="event_id", type="integer", description="ID of the event"),
     *              @OA\Property(property="pass_type", type="string", enum={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}, nullable=true, description="Type of pass"),
     *              @OA\Property(property="block_id", type="integer", nullable=true, description="ID of the block (must belong to a Vaaz Center associated with the event, and to the specified Vaaz Center if vaaz_center_id is also provided)"),
     *              @OA\Property(property="vaaz_center_id", type="integer", nullable=true, description="ID of the Vaaz center (must belong to the specified event)")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Pass preference created successfully",
     *          @OA\JsonContent(type="object", example={"id": 1, "its_id": 12345, "event_id": 1, "pass_type": "RAHAT", "block_id": 1, "vaaz_center_id": 1, "created_at": "2023-01-01T00:00:00.000000Z", "updated_at": "2023-01-01T00:00:00.000000Z"})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error or business logic error (e.g., capacity full, incorrect associations)",
     *          @OA\JsonContent(type="object", example={"its_id": {"The its id has already been taken."}})
     *      )
     * )
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|unique:pass_preferences,its_id',
            'event_id' => 'required|integer|exists:events,id',
            'pass_type' => ['nullable', Rule::enum(PassType::class)],
            'block_id' => 'sometimes|nullable|integer|exists:blocks,id',
            'vaaz_center_id' => 'sometimes|nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();

        // The event_id from validatedData is guaranteed to exist by the 'exists:events,id' validation rule.
        $targetEventIdForComparison = $validatedData['event_id']; 

        // Cross-reference Vaaz Center with the target Event ID
        if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
            $vaazCenter = VaazCenter::find($validatedData['vaaz_center_id']);
            // $vaazCenter will be null if not found; 'exists' validation for vaaz_center_id covers this.
            // We proceed if $vaazCenter is found and its event_id does not match.
            if ($vaazCenter && $vaazCenter->event_id != $targetEventIdForComparison) {
                return response()->json(['message' => 'The selected Vaaz Center does not belong to the specified event.'], 422);
            }
        }

        // Cross-reference Block (if provided)
        if (array_key_exists('block_id', $validatedData) && $validatedData['block_id'] !== null) {
            $block = Block::find($validatedData['block_id']); // $block is guaranteed by 'exists' rule if ID is valid

            // Block must exist and be associated with a VaazCenter for further checks.
            if (!$block || !$block->vaazCenter) {
                return response()->json(['message' => 'Selected Block is invalid or not properly associated with a Vaaz Center.'], 422);
            }

            // 1. Check: Block's own VaazCenter must belong to the target Event.
            if ($block->vaazCenter->event_id != $targetEventIdForComparison) {
                return response()->json(['message' => 'The selected Block (via its Vaaz Center) does not belong to the specified event.'], 422);
            }

            // 2. Check: If a vaaz_center_id was ALSO explicitly provided in the request for the PassPreference (and is not null),
            //    ensure this Block belongs to THAT specific VaazCenter.
            if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
                if ($block->vaaz_center_id != $validatedData['vaaz_center_id']) {
                    return response()->json(['message' => 'The selected Block does not belong to the specified Vaaz Center.'], 422);
                }
            }
        }

        // Vaaz Center Capacity Check (only if vaaz_center_id is provided and not null)
        if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
            $vaazCenter = VaazCenter::find($validatedData['vaaz_center_id']);
            // 'exists' validation handles if $vaazCenter is null, but defensive check is fine.
            if ($vaazCenter) { 
                $currentPassesInCenter = PassPreference::where('vaaz_center_id', $vaazCenter->id)->count();
                if ($vaazCenter->est_capacity > 0 && $currentPassesInCenter >= $vaazCenter->est_capacity) {
                    return response()->json(['message' => 'Selected Vaaz Center is full. Cannot issue more passes.'], 422);
                }
            }
        }

        // Block Capacity Check (only if block_id is provided and not null)
        if (array_key_exists('block_id', $validatedData) && $validatedData['block_id'] !== null) {
            $block = Block::find($validatedData['block_id']);
            // 'exists' validation handles if $block is null, but defensive check is fine.
            if ($block) {
                $currentPassesCount = PassPreference::where('block_id', $block->id)->count();
                // Ensure block capacity is a positive number before checking
                if ($block->capacity > 0 && $currentPassesCount >= $block->capacity) {
                    return response()->json(['message' => 'Selected block is full. Cannot issue more passes.'], 422);
                }
            }
        }

        // Eloquent will handle missing keys by inserting NULL if the DB column is nullable.
        $passPreference = PassPreference::create($validatedData);

        return response()->json($passPreference->load(['block', 'vaazCenter']), 201);
    }

    /**
     * @OA\Put(
     *      path="/api/pass-preferences",
     *      operationId="updatePassPreference",
     *      tags={"Pass Preferences"},
     *      summary="Update an existing pass preference",
     *      description="Updates an existing pass preference record identified by its ITS ID. The ITS ID in the body is expected to be encrypted.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Pass preference data to update. its_id is required to identify the record.",
     *          @OA\JsonContent(
     *              required={"its_id"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID of the mumineen whose preference is to be updated."),
     *              @OA\Property(property="event_id", type="integer", nullable=true, description="ID of the event"),
     *              @OA\Property(property="pass_type", type="string", enum={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}, nullable=true, description="Type of pass"),
     *              @OA\Property(property="block_id", type="integer", nullable=true, description="ID of the block"),
     *              @OA\Property(property="vaaz_center_id", type="integer", nullable=true, description="ID of the Vaaz center")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Pass preference updated successfully",
     *          @OA\JsonContent(type="object", example={"id": 1, "its_id": 12345, "event_id": 1, "pass_type": "RAHAT", "block_id": 1, "vaaz_center_id": 1, "created_at": "2023-01-01T00:00:00.000000Z", "updated_at": "2023-01-01T00:00:00.000000Z"})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Pass Preference not found",
     *          @OA\JsonContent(type="object", example={"message": "Pass Preference not found for the given ITS number."})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error or business logic error",
     *          @OA\JsonContent(type="object", example={"event_id": {"The selected event id is invalid."}})
     *      )
     * )
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|exists:pass_preferences,its_id',
            'event_id' => 'sometimes|required|integer|exists:events,id', // Required if present
            'pass_type' => ['sometimes', 'nullable', Rule::enum(PassType::class)],
            'block_id' => 'sometimes|nullable|integer|exists:blocks,id',
            'vaaz_center_id' => 'sometimes|nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $passPreference = PassPreference::where('its_id', $validatedData['its_id'])->first();

        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found for the given ITS number.'], 404);
        }

        // Determine the target event_id for cross-referencing
        $targetEventId = array_key_exists('event_id', $validatedData) ? $validatedData['event_id'] : $passPreference->event_id;
        $targetEvent = Event::find($targetEventId);

        if (!$targetEvent) {
            // This should ideally be caught by 'exists:events,id' validation if event_id is being updated
            // Or implies an issue if we are using an existing passPreference's event_id that's somehow invalid.
            return response()->json(['message' => 'Target event not found for cross-referencing.'], 422); 
        }

        // Cross-reference Vaaz Center with the target Event
        if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
            $vaazCenter = VaazCenter::find($validatedData['vaaz_center_id']);
            if ($vaazCenter && $vaazCenter->event_id != $targetEvent->id) {
                return response()->json(['message' => 'The selected Vaaz Center does not belong to the target event.'], 422);
            }
        }

        // Cross-reference Block with the target Event (via Block's VaazCenter)
        if (array_key_exists('block_id', $validatedData) && $validatedData['block_id'] !== null) {
            $block = Block::find($validatedData['block_id']);
            // Ensure block exists and is associated with a VaazCenter that belongs to the target event
            if ($block && (!$block->vaazCenter || $block->vaazCenter->event_id != $targetEvent->id)) {
                return response()->json(['message' => 'The selected Block does not belong to the target event.'], 422);
            }
        }

        // Vaaz Center Capacity Check (if center is being changed or set)
        if (array_key_exists('vaaz_center_id', $validatedData)) {
            $newVaazCenterId = $validatedData['vaaz_center_id'];
            if ($passPreference->vaaz_center_id != $newVaazCenterId && $newVaazCenterId !== null) {
                $newVaazCenter = VaazCenter::find($newVaazCenterId);
                if ($newVaazCenter) {
                    $currentPassesInNewCenter = PassPreference::where('vaaz_center_id', $newVaazCenter->id)->count();
                    if ($newVaazCenter->est_capacity > 0 && $currentPassesInNewCenter >= $newVaazCenter->est_capacity) {
                        return response()->json(['message' => 'New selected Vaaz Center is full. Cannot move pass.'], 422);
                    }
                }
            }
        }

        // If block_id is being changed, check capacity of the new block
        if (array_key_exists('block_id', $validatedData)) {
            $newBlockId = $validatedData['block_id'];
            if ($passPreference->block_id != $newBlockId && $newBlockId !== null) {
                $newBlock = Block::find($newBlockId);
                if (!$newBlock) {
                    return response()->json(['message' => 'New selected block not found.'], 404);
                }

                $currentPassesInNewBlock = PassPreference::where('block_id', $newBlock->id)->count();
                if ($currentPassesInNewBlock >= $newBlock->capacity) {
                    return response()->json(['message' => 'New selected block is full. Cannot move pass.'], 422);
                }
            }
        }

        // Update fields if they are present in the validated data
        if (array_key_exists('block_id', $validatedData)) {
            $passPreference->block_id = $validatedData['block_id'];
        }
        if (array_key_exists('vaaz_center_id', $validatedData)) {
            $passPreference->vaaz_center_id = $validatedData['vaaz_center_id'];
        }
        if (array_key_exists('event_id', $validatedData)) { // Add event_id update
            $passPreference->event_id = $validatedData['event_id'];
        }
        
        $passPreference->save();

        return response()->json($passPreference->load(['block', 'vaazCenter']));
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
     *          response=204,
     *          description="Pass preference deleted successfully"
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
            return response()->json($validator->errors(), 422); // Or 400 for bad request format
        }

        $passPreference = PassPreference::where('its_id', $request->input('its_id'))->first();

        // This check is technically covered by 'exists' rule, but good for explicit error message
        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found for the given ITS number.'], 404);
        }

        $passPreference->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *      path="/api/pass-types",
     *      operationId="getAvailablePassTypes",
     *      tags={"Pass Preferences"},
     *      summary="Get all available pass types",
     *      description="Returns a list of all available pass types defined in the PassType enum.",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(type="string"),
     *              example={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}
     *          )
     *      )
     * )
     * Get all available pass types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPassTypes(): JsonResponse
    {
        $passTypes = array_map(fn($case) => $case->value, PassType::cases());
        return response()->json($passTypes);
    }
}
