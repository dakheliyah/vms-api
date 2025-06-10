<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Mumineen;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *     title="Mumineen API",
 *     version="1.0.0",
 *     description="API for managing Mumineen records"
 * )
 */
class MumineenController extends Controller
{
    /**
     * Display a listing of the Mumineen.
     * 
     * @OA\Get(
     *     path="/api/mumineen",
     *     tags={"Mumineen"},
     *     summary="Get all Mumineen records",
     *     description="Returns all Mumineen records from the database",
     *     operationId="getMumineenList",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Mumineen"))
     *     )
     * )
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $mumineen = Mumineen::all();
        return response()->json([
            'success' => true,
            'data' => $mumineen,
            'message' => 'Mumineen records retrieved successfully'
        ]);
    }

    /**
     * Store a newly created Mumineen record in database.
     * 
     * @OA\Post(
     *     path="/api/mumineen",
     *     tags={"Mumineen"},
     *     summary="Create a new Mumineen record",
     *     description="Store a new Mumineen record in the database",
     *     operationId="storeMumineen",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Mumineen data",
     *         @OA\JsonContent(
     *             required={"its_id", "full_name", "gender"},
     *             @OA\Property(property="its_id", type="string", example="ITS123456"),
     *             @OA\Property(property="eits_id", type="string", example="EITS123456"),
     *             @OA\Property(property="hof_its_id", type="string", example="HOF123456"),
     *             @OA\Property(property="full_name", type="string", example="John Doe"),
     *             @OA\Property(property="gender", type="string", example="male", enum={"male", "female", "other"}),
     *             @OA\Property(property="age", type="integer", example=30),
     *             @OA\Property(property="mobile", type="string", example="+1234567890"),
     *             @OA\Property(property="country", type="string", example="United States")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Mumineen record created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Mumineen")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
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
            'hof_its_id' => 'nullable|integer|digits:8',
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
     *     path="/api/mumineen/{its_id}",
     *     tags={"Mumineen"},
     *     summary="Get a Mumineen record",
     *     description="Returns a specific Mumineen record by ITS ID",
     *     operationId="getMumineen",
     *     @OA\Parameter(
     *         name="its_id",
     *         in="path",
     *         description="ITS ID of Mumineen to retrieve",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Mumineen")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Mumineen not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Mumineen not found")
     *         )
     *     )
     * )
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
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
     *     path="/api/mumineen/{its_id}",
     *     tags={"Mumineen"},
     *     summary="Update a Mumineen record",
     *     description="Update a specific Mumineen record by ITS ID",
     *     operationId="updateMumineen",
     *     @OA\Parameter(
     *         name="its_id",
     *         in="path",
     *         description="ITS ID of Mumineen to update",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Mumineen data",
     *         @OA\JsonContent(
     *             @OA\Property(property="eits_id", type="string", example="EITS123456"),
     *             @OA\Property(property="hof_its_id", type="string", example="HOF123456"),
     *             @OA\Property(property="full_name", type="string", example="John Doe"),
     *             @OA\Property(property="gender", type="string", example="male", enum={"male", "female", "other"}),
     *             @OA\Property(property="age", type="integer", example=30),
     *             @OA\Property(property="mobile", type="string", example="+1234567890"),
     *             @OA\Property(property="country", type="string", example="United States")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mumineen record updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Mumineen")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Mumineen not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
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
            'hof_its_id' => 'nullable|integer|digits:8',
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
     *     path="/api/mumineen/{its_id}",
     *     tags={"Mumineen"},
     *     summary="Delete a Mumineen record",
     *     description="Delete a specific Mumineen record by ITS ID",
     *     operationId="deleteMumineen",
     *     @OA\Parameter(
     *         name="its_id",
     *         in="path",
     *         description="ITS ID of Mumineen to delete",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mumineen record deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Mumineen not found"
     *     )
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
     *     path="/api/mumineen/family-by-its-id/{its_id}",
     *     tags={"Mumineen"},
     *     summary="Get all family members by its_id",
     *     description="Finds the HOF ITS ID for the given member and returns all members sharing that HOF ITS ID",
     *     operationId="getMumineenFamilyByItsId",
     *     @OA\Parameter(
     *         name="its_id",
     *         in="path",
     *         description="ITS ID of the Mumineen to find family members for",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64", example=20324227)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Mumineen")),
     *             @OA\Property(property="message", type="string", example="Family members retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Mumineen not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Mumineen not found or no HOF ITS ID available")
     *         )
     *     )
     * )
     *
     * @param string $id
     * @return JsonResponse
     */
    public function getFamilyByItsId(string $id): JsonResponse
    {
        // Find the member by its_id
        $mumineen = Mumineen::where('its_id', $id)->first();

        if (!$mumineen) {
            return response()->json([
                'success' => false,
                'message' => 'Mumineen not found'
            ], 404);
        }
        
        // Get the HOF ITS ID - either the member's own HOF ID or if they are the HOF themselves
        $hofItsId = $mumineen->hof_its_id ?? $mumineen->its_id;
        
        if (!$hofItsId) {
            return response()->json([
                'success' => false,
                'message' => 'No HOF ITS ID available for this member'
            ], 404);
        }
        
        // Find all members who share the same HOF ITS ID
        $familyMembers = Mumineen::where('hof_its_id', $hofItsId)
            ->orWhere('its_id', $hofItsId) // Include the head of family as well
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $familyMembers,
            'message' => 'Family members retrieved successfully'
        ]);
    }
}
