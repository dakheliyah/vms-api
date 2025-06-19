<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\Mumineen; // Added for family check
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Added for auth user
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="Accommodation",
 *   type="object",
 *   required={"miqaat_id", "its_id", "type", "name", "address"},
 *   @OA\Property(property="id", type="integer", readOnly=true, example=1),
 *   @OA\Property(property="miqaat_id", type="integer", example=1),
 *   @OA\Property(property="its_id", type="integer", description="Decrypted ITS ID", example=12345678),
 *   @OA\Property(property="type", type="string", example="Hotel"),
 *   @OA\Property(property="name", type="string", example="Grand Hyatt"),
 *   @OA\Property(property="address", type="string", example="123 Main St, Colombo"),
 *   @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class AccommodationController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/accommodations",
     *      operationId="getAccommodationsList",
     *      tags={"Accommodations"},
     *      summary="Get list of accommodations",
     *      description="Returns list of accommodations, optionally filtered by miqaat_id, its_id (encrypted), or type.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="miqaat_id", in="query", description="Filter by Miqaat ID", required=false, @OA\Schema(type="integer")),
     *      @OA\Parameter(name="its_id", in="query", description="Filter by encrypted ITS ID (will be decrypted by middleware)", required=false, @OA\Schema(type="string")),
     *      @OA\Parameter(name="type", in="query", description="Filter by accommodation type", required=false, @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Accommodation"))
     *       ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized for the provided ITS ID or to list general accommodations without specific ITS ID filter if not admin (not implemented yet)")
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Accommodation::query();

        if ($request->has('its_id')) {
            $targetItsId = $request->input('its_id');
            if (!$this->isFamilyMember($targetItsId)) {
                return response()->json(['message' => 'You are not authorized to view accommodations for this ITS ID.'], 403);
            }
            $query->where('its_id', $targetItsId);
        } else {
            // If no specific ITS ID is requested, list for the user and their family
            $familyItsIds = $this->getFamilyItsIds($user);
            if (empty($familyItsIds)) { // Should not happen for an authenticated user
                 return response()->json(['message' => 'Could not determine family ITS IDs for authorization.'], 403);
            }
            $query->whereIn('its_id', $familyItsIds);
        }

        if ($request->has('miqaat_id')) {
            $query->where('miqaat_id', $request->input('miqaat_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        return response()->json($query->get());
    }

    /**
     * @OA\Post(
     *      path="/api/accommodations",
     *      operationId="storeAccommodation",
     *      tags={"Accommodations"},
     *      summary="Create new accommodation record",
     *      description="Creates a new accommodation record. 'its_id' is expected to be encrypted.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Accommodation object that needs to be added",
     *          @OA\JsonContent(
     *              required={"miqaat_id", "its_id", "type", "name", "address"},
     *              @OA\Property(property="miqaat_id", type="integer", example=1),
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID", example="encrypted_its_string"),
     *              @OA\Property(property="type", type="string", example="Hotel"),
     *              @OA\Property(property="name", type="string", example="Grand Hyatt"),
     *              @OA\Property(property="address", type="string", example="123 Main St, Colombo")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Accommodation created successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Accommodation")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized to create accommodation for this ITS ID"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $targetItsId = $request->input('its_id');
        // its_id from input is already decrypted by DecryptItsIdMiddleware if it was part of the specified fields.
        // Ensure DecryptItsIdMiddleware is configured for 'its_id' in POST body for this route.
        // For now, we assume $targetItsId is the decrypted integer value.

        if (!$this->isFamilyMember((int)$targetItsId)) {
            return response()->json(['message' => 'You are not authorized to create an accommodation for this ITS ID.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'miqaat_id' => 'required|integer', // Add exists rule if miqaats table is present
            'its_id' => 'required|integer|in:'.$targetItsId, // Ensure the validated its_id is the one authorized
            'type' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $accommodation = Accommodation::create($validator->validated());

        return response()->json($accommodation, 201);
    }

    /**
     * @OA\Get(
     *      path="/api/accommodations/{id}",
     *      operationId="getAccommodationById",
     *      tags={"Accommodations"},
     *      summary="Get accommodation information",
     *      description="Returns accommodation data by ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", description="ID of accommodation to return", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/Accommodation")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized to view this accommodation"),
     *      @OA\Response(response=404, description="Resource Not Found")
     * )
     */
    public function show(Accommodation $accommodation)
    {
        if (!$this->isFamilyMember($accommodation->its_id)) {
            return response()->json(['message' => 'You are not authorized to view this accommodation.'], 403);
        }
        return response()->json($accommodation);
    }

    /**
     * @OA\Put(
     *      path="/api/accommodations/{id}",
     *      operationId="updateAccommodation",
     *      tags={"Accommodations"},
     *      summary="Update existing accommodation record",
     *      description="Updates an existing accommodation record. 'its_id' is expected to be encrypted if provided.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", description="ID of accommodation to update", required=true, @OA\Schema(type="integer")),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Accommodation object that needs to be updated",
     *          @OA\JsonContent(
     *              @OA\Property(property="miqaat_id", type="integer", example=1),
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID", example="encrypted_its_string"),
     *              @OA\Property(property="type", type="string", example="Apartment"),
     *              @OA\Property(property="name", type="string", example="Serviced Apartment Colombo"),
     *              @OA\Property(property="address", type="string", example="456 Galle Rd, Colombo")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Accommodation updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Accommodation")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized to update this accommodation or assign to the target ITS ID"),
     *      @OA\Response(response=404, description="Resource Not Found"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Accommodation $accommodation)
    {
        if (!$this->isFamilyMember($accommodation->its_id)) {
            return response()->json(['message' => 'You are not authorized to update this accommodation.'], 403);
        }

        $validatedData = $request->validate([
            'miqaat_id' => 'sometimes|required|integer',
            'its_id' => 'sometimes|required|integer', // This is the new ITS ID, already decrypted if sent encrypted
            'type' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string',
        ]);

        // If its_id is being changed, verify authorization for the new its_id
        if ($request->has('its_id') && $request->input('its_id') != $accommodation->its_id) {
            $newItsId = $request->input('its_id'); // Already decrypted integer
            if (!$this->isFamilyMember((int)$newItsId)) {
                return response()->json(['message' => 'You are not authorized to assign this accommodation to the target ITS ID.'], 403);
            }
            // Ensure the validator's 'its_id' is used if provided, otherwise keep original
             $validatedData['its_id'] = (int)$newItsId;
        } else {
            // If its_id is not in the request, remove it from validatedData to prevent it from being updated to null or 0 unintentionally
            // Or ensure it keeps its original value if not changing
            unset($validatedData['its_id']);
        }

        $accommodation->update($validatedData);

        return response()->json($accommodation);
    }

    /**
     * @OA\Delete(
     *      path="/api/accommodations/{id}",
     *      operationId="deleteAccommodation",
     *      tags={"Accommodations"},
     *      summary="Delete accommodation record",
     *      description="Deletes an accommodation record by ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", description="ID of accommodation to delete", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(
     *          response=200,
     *          description="Accommodation deleted successfully",
     *          @OA\JsonContent(type="object", @OA\Property(property="message", type="string", example="Accommodation deleted successfully"))
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - User not authorized to delete this accommodation"),
     *      @OA\Response(response=404, description="Resource Not Found")
     * )
     */
    public function destroy(Accommodation $accommodation)
    {
        if (!$this->isFamilyMember($accommodation->its_id)) {
            return response()->json(['message' => 'You are not authorized to delete this accommodation.'], 403);
        }

        $accommodation->delete();

        return response()->json(['message' => 'Accommodation deleted successfully']);
    }

    /**
     * Check if the target ITS ID belongs to the authenticated user or their family.
     * @param int $targetItsId The ITS ID to check.
     * @return bool True if authorized, false otherwise.
     */
    private function isFamilyMember($targetItsId)
    {
        $user = Auth::user();
        if (!$user) {
            return false; // Should not happen if auth:api middleware is applied
        }

        $familyItsIds = $this->getFamilyItsIds($user);
        return in_array($targetItsId, $familyItsIds);
    }

    /**
     * Get the authenticated user's ITS ID and their family members' ITS IDs.
     * @param \App\Models\User $user The authenticated user.
     * @return array An array of ITS IDs.
     */
    private function getFamilyItsIds($user)
    {
        // Attempt to get from JWT custom claim first (e.g., 'family_its_ids')
        // This claim should be populated during login by AuthController.
        if (isset($user->family_its_ids) && is_array($user->family_its_ids)) {
            // Ensure user's own ITS is also part of this, if not already by claim logic
            return array_unique(array_merge([$user->its_id], $user->family_its_ids));
        }

        // Fallback: Query Mumineen table if family_its_ids is not readily available from token/user object
        // This requires the User model to have an 'its_id' attribute and Mumineen model setup.
        $loggedInUserItsId = $user->its_id; // Assuming User model has its_id attribute
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
