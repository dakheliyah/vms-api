<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Mumineen;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MumineenController extends Controller
{
    /**
     * Display either a listing of all Mumineen or a specific one based on query parameter.
     * 
     * @OA\Get(
     *      path="/api/mumineen",
     *      operationId="getMumineenOrList",
     *      tags={"Mumineen"},
     *      summary="Get all Mumineen records or a specific one",
     *      description="If an encrypted ITS ID is provided in the query, it returns the specific Mumineen record. If no ITS ID is provided, an empty response is returned. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="query",
     *          description="Encrypted ITS ID of the Mumineen to retrieve (optional).",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(type="object", example={"success": true, "data": {}})
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Mumineen not found",
     *          @OA\JsonContent(type="object", example={"success": false, "message": "Mumineen not found"})
     *      )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function indexOrShow(Request $request): JsonResponse
    {
        // Check if its_id parameter is present
        $id = $request->input('user_decrypted_its_id');
        
        if (empty($id)) {
            // If no its_id provided, return an empty successful response.
            // A mumin can only see their family info; an ITS ID is required to specify whose info.
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Please provide an ITS ID to retrieve specific Mumineen information.'
            ]);
        } else {
            // If its_id is provided, return specific record (like the original show method)
            $mumineen = Mumineen::where('its_id', $id)->first();
            
            if (!$mumineen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mumineen not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $mumineen,
                'message' => 'Mumineen record retrieved successfully'
            ]);
        }
    }

    /**
     * Store a newly created Mumineen record in database.
     * 
     * @OA\Post(
     *      path="/api/mumineen",
     *      operationId="storeMumineen",
     *      tags={"Mumineen"},
     *      summary="Create a new Mumineen record",
     *      description="Stores a new Mumineen record. The 'its_id' is expected to be encrypted. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Mumineen data object. `its_id` must be unique.",
     *          @OA\JsonContent(
     *              required={"its_id", "full_name", "gender"},
     *              @OA\Property(property="its_id", type="string", description="Encrypted ITS ID. Must be unique.", example="encrypted_string"),
     *              @OA\Property(property="hof_id", type="string", description="Encrypted ITS ID of the Head of Family.", example="encrypted_string"),
     *              @OA\Property(property="full_name", type="string", example="John Doe"),
     *              @OA\Property(property="gender", type="string", example="male", enum={"male", "female"}),
     *              @OA\Property(property="age", type="integer", example=30),
     *              @OA\Property(property="mobile", type="string", example="1234567890"),
     *              @OA\Property(property="country", type="string", example="United States")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Mumineen record created successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Mumineen")
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(type="object", example={"message": "The given data was invalid.", "errors": {}})
     *      )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|digits:8|unique:mumineens,its_id',
            'eits_id' => 'nullable|integer|digits:8',
            'hof_id' => 'nullable|integer|digits:8',
            'full_name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'age' => 'nullable|integer',
            'mobile' => 'nullable|string',
            'country' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $mumineen = Mumineen::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $mumineen,
            'message' => 'Mumineen record created successfully'
        ], 201);
    }

    /**
     * Display the specified Mumineen record.
     * 
     * @OA\Get(
     *      path="/api/mumineen/{its_id}",
     *      operationId="getMumineen",
     *      tags={"Mumineen"},
     *      summary="Get a specific Mumineen record",
     *      description="Returns a specific Mumineen record by their encrypted ITS ID. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="path",
     *          description="Encrypted ITS ID of the Mumineen to retrieve.",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/Mumineen")
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Mumineen not found",
     *          @OA\JsonContent(type="object", example={"success": false, "message": "Mumineen not found"})
     *      )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        // Get the its_id from query parameters
        $id = $request->input('user_decrypted_its_id');
        
        if (empty($id)) {
            return response()->json([
                'success' => false,
                'message' => 'its_id parameter is required'
            ], 400);
        }
        
        $mumineen = Mumineen::where('its_id', $id)->first();

        if (!$mumineen) {
            return response()->json([
                'success' => false,
                'message' => 'Mumineen not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mumineen,
            'message' => 'Mumineen record retrieved successfully'
        ]);
    }

    /**
     * Update the specified Mumineen record.
     * 
     * @OA\Put(
     *      path="/api/mumineen/{its_id}",
     *      operationId="updateMumineen",
     *      tags={"Mumineen"},
     *      summary="Update a Mumineen record",
     *      description="Updates a specific Mumineen record by their encrypted ITS ID. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="path",
     *          description="Encrypted ITS ID of the Mumineen to update.",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Mumineen data to update.",
     *          @OA\JsonContent(
     *              @OA\Property(property="hof_id", type="string", description="Encrypted ITS ID of the Head of Family.", example="encrypted_string"),
     *              @OA\Property(property="full_name", type="string", example="John Doe"),
     *              @OA\Property(property="gender", type="string", example="male", enum={"male", "female"}),
     *              @OA\Property(property="age", type="integer", example=31),
     *              @OA\Property(property="mobile", type="string", example="1234567890"),
     *              @OA\Property(property="country", type="string", example="United States")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Mumineen record updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Mumineen")
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Mumineen not found"
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'eits_id' => 'nullable|integer|digits:8',
            'hof_id' => 'nullable|integer|digits:8',
            'full_name' => 'string|max:255',
            'gender' => 'in:male,female,other',
            'age' => 'nullable|integer',
            'mobile' => 'nullable|string',
            'country' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $mumineen = Mumineen::where('its_id', $id)->first();

        if (!$mumineen) {
            return response()->json([
                'success' => false,
                'message' => 'Mumineen not found'
            ], 404);
        }

        $mumineen->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $mumineen,
            'message' => 'Mumineen record updated successfully'
        ]);
    }

    /**
     * Remove the specified Mumineen record.
     * 
     * @OA\Delete(
     *      path="/api/mumineen/{its_id}",
     *      operationId="deleteMumineen",
     *      tags={"Mumineen"},
     *      summary="Delete a Mumineen record",
     *      description="Deletes a specific Mumineen record by their encrypted ITS ID. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="path",
     *          description="Encrypted ITS ID of the Mumineen to delete.",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Mumineen record deleted successfully",
     *          @OA\JsonContent(type="object", example={"success": true, "message": "Mumineen record deleted successfully"})
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Mumineen not found"
     *      )
     * )
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $mumineen = Mumineen::where('its_id', $id)->first();

        if (!$mumineen) {
            return response()->json([
                'success' => false,
                'message' => 'Mumineen not found'
            ], 404);
        }

        $mumineen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mumineen record deleted successfully'
        ]);
    }
    
    /**
     * Get all family members of a Mumineen by its_id.
     * 
     * @OA\Get(
     *      path="/api/mumineen/family-by-its-id/{its_id}",
     *      operationId="getMumineenFamilyByItsId",
     *      tags={"Mumineen"},
     *      summary="Get all family members by ITS ID",
     *      description="Finds the Head of Family (HOF) for the given member (by their encrypted ITS ID) and returns all members of that family. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="its_id",
     *          in="path",
     *          description="Encrypted ITS ID of a family member.",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Family members retrieved successfully",
     *          @OA\JsonContent(type="object", example={"success": true, "data": {}})
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Mumineen not found or no HOF ITS ID available",
     *          @OA\JsonContent(type="object", example={"success": false, "message": "Mumineen not found or no HOF ITS ID available"})
     *      )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getFamilyByItsId(Request $request): JsonResponse
    {
        // Get the its_id from query parameters
        $id = $request->input('user_decrypted_its_id');
        $eventId = $request->input('event_id');
        
        if (!$eventId) {
            return response()->json([
                'success' => false,
                'message' => 'event_id parameter is required'
            ], 400);
        }
        
        // Find the member by its_id
        $mumineen = Mumineen::where('its_id', $id)->first();

        if (!$mumineen) {
            return response()->json([
                'success' => false,
                'message' => 'Mumineen not found'
            ], 404);
        }
        
        // Get the HOF ITS ID - either the member's own HOF ID or if they are the HOF themselves
        $hofItsId = $mumineen->hof_id ?? $mumineen->its_id;
        
        if (!$hofItsId) {
            return response()->json([
                'success' => false,
                'message' => 'No HOF ITS ID available for this member'
            ], 404);
        }
        
        // Find all members who share the same HOF ITS ID
        $familyMembers = Mumineen::where('hof_id', $hofItsId)
            ->orWhere('its_id', $hofItsId) // Include the head of family as well
            ->with(['passPreferences' => function($query) use ($eventId) {
                $query->where('event_id', $eventId);
            }])
            ->get();
            
        // Transform the result to include vaaz center name directly
        $familyMembers->transform(function ($member) {
            // For each pass preference, fetch and add the vaaz center name
            if ($member->passPreferences->isNotEmpty()) {
                $member->passPreferences->transform(function ($preference) {
                    if ($preference->vaaz_center_id) {
                        // Fetch the vaaz center name
                        $vaazCenter = \App\Models\VaazCenter::find($preference->vaaz_center_id);
                        $preference->vaaz_center_name = $vaazCenter ? $vaazCenter->name : null;
                    }
                    return $preference;
                });
            }
            return $member;
        });
        
        return response()->json([
            'success' => true,
            'data' => $familyMembers,
            'message' => 'Family members retrieved successfully'
        ]);
    }

    /**
     * Get all Mumineen with their pass preferences for a specific event.
     *
     * @OA\Get(
     *      path="/api/mumineen/pass-preference/breakdown",
     *      operationId="getMumineenPassPreferenceBreakdown",
     *      tags={"Mumineen"},
     *      summary="Get all Mumineen with their pass preferences for a specific event",
     *      description="Returns a list of all Mumineen records. For each Mumineen, it includes their pass preference details if one exists for the specified event ID.",
     *      @OA\Parameter(
     *          name="event_id",
     *          in="query",
     *          description="ID of the event to filter pass preferences by.",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/Mumineen")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(type="object", example={"message": "The given data was invalid.", "errors": {}})
     *      )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMumineenWithPassesByEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $eventId = $request->input('event_id');

        $allMumineen = Mumineen::with(['passPreferences' => function ($query) use ($eventId) {
            $query->where('event_id', $eventId)
                  ->with(['block', 'vaazCenter']); // Eager load details from pass
        }])->get();

        return response()->json([
            'success' => true,
            'data' => $allMumineen
        ]);
    }

    /**
     * Bulk upload and synchronize Mumineen records from a CSV file.
     *
     * @OA\Post(
     *      path="/api/mumineen/bulk",
     *      operationId="bulkStoreMumineen",
     *      tags={"Mumineen"},
     *      summary="Bulk upload/synchronize Mumineen from a CSV file",
     *      description="Upload a CSV file to add, update, and remove Mumineen records. The CSV is treated as the source of truth. Any Mumineen in the database but not in the CSV will be deleted, along with their pass preferences. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="CSV file to upload. The first row must be a header row with column names matching the `mumineens` table fields (e.g., its_id, hof_id, fullname, etc.).",
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"file"},
     *                  @OA\Property(
     *                      property="file",
     *                      type="string",
     *                      format="binary",
     *                      description="The CSV file."
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Bulk operation completed successfully",
     *          @OA\JsonContent(type="object", example={"success": true, "message": "Bulk upload completed successfully.", "summary": {"processed": 50, "deleted": 5, "updated_or_created": 45}})
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error (e.g., no file, wrong file type)",
     *          @OA\JsonContent(type="object", example={"message": "The given data was invalid.", "errors": {}})
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="An error occurred during the bulk processing",
     *          @OA\JsonContent(type="object", example={"success": false, "message": "An error occurred during bulk processing."})
     *      )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $path = $request->file('file')->getRealPath();
        $file = file($path);
        $csv = array_map('str_getcsv', $file);
        $header = array_shift($csv);

        $csvData = [];
        foreach ($csv as $row) {
            // Skip empty rows
            if (count($row) === 1 && is_null($row[0])) continue;
            $csvData[] = array_combine($header, $row);
        }

        try {
            $summary = \Illuminate\Support\Facades\DB::transaction(function () use ($csvData) {
                $csvItsIds = array_column($csvData, 'its_id');

                // Find and delete Mumineen (and their pass preferences) that are not in the CSV
                $mumineenToDelete = \App\Models\Mumineen::whereNotIn('its_id', $csvItsIds)->pluck('its_id');
                $deletedCount = $mumineenToDelete->count();

                if ($deletedCount > 0) {
                    \App\Models\PassPreference::whereIn('its_id', $mumineenToDelete)->delete();
                    \App\Models\Mumineen::whereIn('its_id', $mumineenToDelete)->delete();
                }

                // Update or create Mumineen from the CSV data
                $upsertedCount = 0;
                $fillableFields = (new \App\Models\Mumineen)->getFillable();
                foreach ($csvData as $data) {
                    // Ensure only fillable fields are used
                    $filteredData = array_intersect_key($data, array_flip($fillableFields));
                    
                    \App\Models\Mumineen::updateOrCreate(['its_id' => $data['its_id']], $filteredData);
                    $upsertedCount++;
                }

                return [
                    'processed' => count($csvData),
                    'deleted' => $deletedCount,
                    'updated_or_created' => $upsertedCount,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Bulk upload completed successfully.',
                'summary' => $summary,
            ]);

        } catch (\Throwable $e) {
            // Log the error for debugging
            error_log($e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred during bulk processing: ' . $e->getMessage()], 500);
        }
    }
}
