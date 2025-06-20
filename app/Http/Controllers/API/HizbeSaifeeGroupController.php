<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HizbeSaifeeGroup;
use Illuminate\Http\Request;
use App\Helpers\AuthorizationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Hizbe Saifee Groups",
 *     description="API Endpoints for Hizbe Saifee Groups Management (Admin Only)"
 * )
 */
class HizbeSaifeeGroupController extends Controller
{
    /**
     * @OA\Get(
     *      path="/hizbe-saifee-groups",
     *      operationId="getHizbeSaifeeGroupsList",
     *      tags={"Hizbe Saifee Groups"},
     *      summary="Get list of Hizbe Saifee groups",
     *      description="Returns list of Hizbe Saifee groups. Admin access required.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/HizbeSaifeeGroup"))
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden (Admin access required)")
     * )
     */
    public function index(Request $request)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }
        return HizbeSaifeeGroup::all();
    }

    /**
     * @OA\Post(
     *      path="/hizbe-saifee-groups",
     *      operationId="storeHizbeSaifeeGroup",
     *      tags={"Hizbe Saifee Groups"},
     *      summary="Store a new Hizbe Saifee group",
     *      description="Stores a new Hizbe Saifee group. Admin access required.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/StoreHizbeSaifeeGroupRequest")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Array of Hizbe Saifee Group objects to create",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/StoreHizbeSaifeeGroupRequest")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/HizbeSaifeeGroup")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden (Admin access required)"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 403);
        }

        $groupsData = $request->input();
        if (!is_array($groupsData) || empty($groupsData)) {
            return response()->json(['success' => false, 'message' => 'Request body must be a non-empty array of group objects.'], 400);
        }

        $createdGroups = [];
        $errors = [];

        // Pre-validation for group_no uniqueness within the batch and DB
        $allGroupNosInRequest = array_filter(array_column($groupsData, 'group_no'));
        if (count($allGroupNosInRequest) !== count(array_unique($allGroupNosInRequest))) {
            return response()->json(['success' => false, 'message' => 'Validation failed. Duplicate group_no found in the request batch.'], 422);
        }
        $existingGroupNos = HizbeSaifeeGroup::whereIn('group_no', $allGroupNosInRequest)->pluck('group_no')->toArray();
        if (!empty($existingGroupNos)) {
            $collidingNos = implode(', ', $existingGroupNos);
            return response()->json(['success' => false, 'message' => "Validation failed. The following group_no already exist: {$collidingNos}."], 422);
        }

        foreach ($groupsData as $index => $groupData) {
            $validator = Validator::make($groupData, [
                'name' => 'required|string|max:255',
                'capacity' => 'required|integer|min:1',
                'group_no' => 'required|integer', // Uniqueness already checked above for the batch
                'whatsapp_link' => 'nullable|url|max:2048',
            ]);

            if ($validator->fails()) {
                $errors['group_' . $index] = $validator->errors();
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'message' => 'Validation failed for one or more groups.', 'errors' => $errors], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($groupsData as $groupData) {
                $createdGroups[] = HizbeSaifeeGroup::create($groupData);
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Hizbe Saifee Groups created successfully.', 'data' => $createdGroups], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create groups due to a server error.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/hizbe-saifee-groups/{id}",
     *      operationId="getHizbeSaifeeGroupById",
     *      tags={"Hizbe Saifee Groups"},
     *      summary="Get Hizbe Saifee group information",
     *      description="Returns Hizbe Saifee group data. Admin access required.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Hizbe Saifee Group id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/HizbeSaifeeGroup")
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden (Admin access required)"),
     *      @OA\Response(response=404, description="Resource Not Found")
     * )
     */
    public function show(Request $request, HizbeSaifeeGroup $hizbeSaifeeGroup)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }
        return $hizbeSaifeeGroup;
    }

    /**
     * @OA\Put(
     *      path="/hizbe-saifee-groups",
     *      operationId="updateHizbeSaifeeGroup",
     *      tags={"Hizbe Saifee Groups"},
     *      summary="Update an existing Hizbe Saifee group",
     *      description="Updates an existing Hizbe Saifee group. Admin access required.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/UpdateHizbeSaifeeGroupRequest")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Array of Hizbe Saifee Group objects to update",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/UpdateHizbeSaifeeGroupRequest")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/HizbeSaifeeGroup")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden (Admin access required)"),
     *      @OA\Response(response=404, description="Resource Not Found"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 403);
        }

        $groupsUpdateData = $request->input();
        if (!is_array($groupsUpdateData) || empty($groupsUpdateData)) {
            return response()->json(['success' => false, 'message' => 'Request body must be a non-empty array of group update objects.'], 400);
        }

        $updatedGroups = [];
        $errors = [];

        // Pre-validation for group_no uniqueness within the batch and DB for updates
        $groupNosInRequest = [];
        foreach ($groupsUpdateData as $updateData) {
            if (isset($updateData['group_no']) && isset($updateData['id'])) {
                $groupNosInRequest[] = ['id' => $updateData['id'], 'group_no' => $updateData['group_no']];
            }
        }

        foreach ($groupNosInRequest as $item) {
            // Check for duplicates within the batch, ignoring self
            $duplicatesInBatch = array_filter($groupNosInRequest, function ($otherItem) use ($item) {
                return $otherItem['group_no'] === $item['group_no'] && $otherItem['id'] !== $item['id'];
            });
            if (count($duplicatesInBatch) > 0) {
                 return response()->json(['success' => false, 'message' => "Validation failed. Duplicate group_no '{$item['group_no']}' found in the request batch for different IDs."], 422);
            }

            // Check against DB, ignoring self
            $existingGroup = HizbeSaifeeGroup::where('group_no', $item['group_no'])->where('id', '!=', $item['id'])->first();
            if ($existingGroup) {
                return response()->json(['success' => false, 'message' => "Validation failed. Group_no '{$item['group_no']}' already exists for another group (ID: {$existingGroup->id})."], 422);
            }
        }

        foreach ($groupsUpdateData as $index => $updateData) {
            if (!isset($updateData['id'])) {
                $errors['group_' . $index] = ['id' => ['The id field is required for each group to update.']];
                continue;
            }

            $groupToUpdate = HizbeSaifeeGroup::find($updateData['id']);
            if (!$groupToUpdate) {
                $errors['group_' . $index] = ['id' => ['Hizbe Saifee Group with id ' . $updateData['id'] . ' not found.']];
                continue;
            }

            $validator = Validator::make($updateData, [
                'name' => 'sometimes|required|string|max:255',
                'capacity' => 'sometimes|required|integer|min:1',
                // group_no uniqueness is pre-validated above
                'group_no' => 'sometimes|required|integer',
                'whatsapp_link' => 'nullable|url|max:2048',
            ]);

            if ($validator->fails()) {
                $errors['group_' . $index] = $validator->errors();
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'message' => 'Validation failed for one or more groups.', 'errors' => $errors], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($groupsUpdateData as $updateData) {
                $group = HizbeSaifeeGroup::find($updateData['id']);
                // ID already validated to exist, so $group will be found
                $group->update($updateData);
                $updatedGroups[] = $group->fresh(); // Get the updated model
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Hizbe Saifee Groups updated successfully.', 'data' => $updatedGroups], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to update groups due to a server error.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *      path="/hizbe-saifee-groups/{id}",
     *      operationId="deleteHizbeSaifeeGroup",
     *      tags={"Hizbe Saifee Groups"},
     *      summary="Delete an existing Hizbe Saifee group",
     *      description="Deletes an existing Hizbe Saifee group. Admin access required.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Hizbe Saifee Group id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(type="object", example={"success": true, "message": "Hizbe Saifee Group deleted successfully."})
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden (Admin access required)"),
     *      @OA\Response(response=404, description="Resource Not Found")
     * )
     */
    public function destroy(Request $request, HizbeSaifeeGroup $hizbeSaifeeGroup)
    {
        if (!AuthorizationHelper::isAdmin($request)) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }

        $hizbeSaifeeGroup->delete();
        return response()->json(['success' => true, 'message' => 'Hizbe Saifee Group deleted successfully.'], 200);
    }
    // Schema definitions for Swagger
}

