<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PassPreference;
use App\Models\VaazCenter;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PassPreferenceController extends Controller
{
    /**
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
            ->with(['blocks' => function ($query) {
                $query->withCount('passPreferences');
            }])
            ->get();

        $summary = $vaazCenters->map(function ($vaazCenter) {
            return [
                'id' => $vaazCenter->id,
                'name' => $vaazCenter->name,
                'blocks' => $vaazCenter->blocks->map(function ($block) {
                    return [
                        'id' => $block->id,
                        'type' => $block->type,
                        'capacity' => $block->capacity,
                        'gender' => $block->gender,
                        'min_age' => $block->min_age,
                        'max_age' => $block->max_age,
                        'issued_passes' => $block->pass_preferences_count,
                        'availability' => $block->capacity - $block->pass_preferences_count,
                    ];
                }),
            ];
        });

        return response()->json($summary);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexOrShow(Request $request)
    {
        if ($request->has('its_id')) {
            $passPreference = PassPreference::where('its_id', $request->input('its_id'))->with('block')->first(); // Eager load block
            if (!$passPreference) {
                return response()->json(['message' => 'Pass Preference not found for this ITS number'], 404);
            }
            return response()->json($passPreference);
        }

        // Consider pagination for `all()` if the list can grow large
        return PassPreference::with('block')->get(); // Eager load block for all
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|unique:pass_preferences,its_id',
            'block_id' => 'required|exists:blocks,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $block = Block::find($validatedData['block_id']);

        if (!$block) { // Should not happen due to 'exists' rule, but good practice
            return response()->json(['message' => 'Selected block not found.'], 404);
        }

        // Capacity Check
        $currentPassesCount = PassPreference::where('block_id', $block->id)->count();
        if ($currentPassesCount >= $block->capacity) {
            return response()->json(['message' => 'Selected block is full. Cannot issue more passes.'], 422);
        }

        $passPreference = PassPreference::create($validatedData);

        return response()->json($passPreference, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|exists:pass_preferences,its_id',
            'block_id' => 'required|exists:blocks,id', // User must always provide the new block_id
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $passPreference = PassPreference::where('its_id', $validatedData['its_id'])->first();

        // This check is technically covered by 'exists:pass_preferences,its_id' but good for clarity
        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found for the given ITS number.'], 404);
        }

        // If block_id is being changed, check capacity of the new block
        if ($passPreference->block_id != $validatedData['block_id']) {
            $newBlock = Block::find($validatedData['block_id']);
            if (!$newBlock) { // Should not happen due to 'exists' rule
                return response()->json(['message' => 'New selected block not found.'], 404);
            }

            $currentPassesInNewBlock = PassPreference::where('block_id', $newBlock->id)->count();
            if ($currentPassesInNewBlock >= $newBlock->capacity) {
                return response()->json(['message' => 'New selected block is full. Cannot move pass.'], 422);
            }
        }

        // Update only the block_id. ITS_ID is the identifier and should not be changed here.
        $passPreference->block_id = $validatedData['block_id'];
        $passPreference->save();

        return response()->json($passPreference);
    }

    /**
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
}
