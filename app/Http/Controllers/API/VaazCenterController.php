<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Helpers\AuthorizationHelper;
use App\Models\VaazCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VaazCenterController extends Controller
{
    /**
     * Display a listing of the resource or a single resource.
     */
    /**
     * Display a listing of Vaaz Centers or a single Vaaz Center.
     *
     * @OA\Get(
     *      path="/api/vaaz-centers",
     *      operationId="getVaazCentersListOrSingle",
     *      tags={"Vaaz Centers"},
     *      summary="Get list of Vaaz Centers or a single one by ID",
     *      description="Returns a list of Vaaz Centers. Can be filtered by event_id or a single Vaaz Center by id. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="query",
     *          description="ID of the Vaaz Center to retrieve.",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="event_id",
     *          in="query",
     *          description="Filter Vaaz Centers by Event ID.",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/VaazCenter")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Vaaz Center not found (if ID is provided and not found)"
     *      )
     * )
     */
    public function indexOrShow(Request $request)
    {
        $query = VaazCenter::with('event');

        if ($request->has('id')) {
            $vaazCenter = $query->find($request->input('id'));
            if (!$vaazCenter) {
                return response()->json(['message' => 'Vaaz Center not found'], 404);
            }
            return response()->json($vaazCenter);
        }

        if ($request->has('event_id')) {
            $query->where('event_id', $request->input('event_id'));
        }

        return $query->get();
    }

    /**
     * Store a newly created Vaaz Center in storage.
     *
     * @OA\Post(
     *      path="/api/vaaz-centers",
     *      operationId="storeVaazCenter",
     *      tags={"Vaaz Centers"},
     *      summary="Create a new Vaaz Center",
     *      description="Stores a new Vaaz Center record. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Vaaz Center data object.",
     *          @OA\JsonContent(
     *              required={"name", "est_capacity"},
     *              @OA\Property(property="name", type="string", example="Al Masjid Al Husaini"),
     *              @OA\Property(property="est_capacity", type="integer", example=1000, description="Estimated total capacity."),
     *              @OA\Property(property="male_capacity", type="integer", nullable=true, example=500, description="Estimated male capacity."),
     *              @OA\Property(property="female_capacity", type="integer", nullable=true, example=500, description="Estimated female capacity."),
     *              @OA\Property(property="lat", type="number", format="float", nullable=true, example=6.9271, description="Latitude."),
     *              @OA\Property(property="long", type="number", format="float", nullable=true, example=79.8612, description="Longitude."),
     *              @OA\Property(property="event_id", type="integer", nullable=true, example=1, description="Associated Event ID.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Vaaz Center created successfully",
     *          @OA\JsonContent(ref="#/components/schemas/VaazCenter")
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
     */
    public function store(Request $request)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'est_capacity' => 'required|integer',
            'male_capacity' => 'nullable|integer|min:0',
            'female_capacity' => 'nullable|integer|min:0',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
            'event_id' => 'nullable|exists:events,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $vaazCenter = VaazCenter::create($validator->validated());

        return response()->json($vaazCenter, 201);
    }

    /**
     * Update the specified Vaaz Center in storage.
     *
     * @OA\Put(
     *      path="/api/vaaz-centers/{id}",
     *      operationId="updateVaazCenter",
     *      tags={"Vaaz Centers"},
     *      summary="Update an existing Vaaz Center",
     *      description="Updates an existing Vaaz Center record. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="ID of the Vaaz Center to update.",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Vaaz Center data to update.",
     *          @OA\JsonContent(
     *              @OA\Property(property="name", type="string", example="Al Masjid Al Husaini - Updated"),
     *              @OA\Property(property="est_capacity", type="integer", example=1200, description="Estimated total capacity."),
     *              @OA\Property(property="male_capacity", type="integer", nullable=true, example=600, description="Estimated male capacity."),
     *              @OA\Property(property="female_capacity", type="integer", nullable=true, example=600, description="Estimated female capacity."),
     *              @OA\Property(property="lat", type="number", format="float", nullable=true, example=6.9275, description="Latitude."),
     *              @OA\Property(property="long", type="number", format="float", nullable=true, example=79.8615, description="Longitude."),
     *              @OA\Property(property="event_id", type="integer", nullable=true, example=1, description="Associated Event ID.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Vaaz Center updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/VaazCenter")
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Vaaz Center not found"
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     */
    public function update(Request $request, $id)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }
        $vaazCenter = VaazCenter::find($id);
        if (!$vaazCenter) {
            return response()->json(['message' => 'Vaaz Center not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'est_capacity' => 'sometimes|required|integer',
            'male_capacity' => 'nullable|integer|min:0',
            'female_capacity' => 'nullable|integer|min:0',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
            'event_id' => 'nullable|exists:events,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $vaazCenter->update($validator->validated());

        return response()->json($vaazCenter);
    }

    /**
     * Remove the specified Vaaz Center from storage.
     *
     * @OA\Delete(
     *      path="/api/vaaz-centers/{id}",
     *      operationId="deleteVaazCenter",
     *      tags={"Vaaz Centers"},
     *      summary="Delete a Vaaz Center",
     *      description="Deletes a Vaaz Center by its ID. Requires authentication.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="ID of the Vaaz Center to delete.",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Vaaz Center deleted successfully"
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Vaaz Center not found"
     *      )
     * )
     */
    public function destroy($id, Request $request)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }
        $vaazCenter = VaazCenter::find($id);
        if (!$vaazCenter) {
            return response()->json(['message' => 'Vaaz Center not found'], 404);
        }

        $vaazCenter->delete();

        return response()->json(null, 204);
    }
}
