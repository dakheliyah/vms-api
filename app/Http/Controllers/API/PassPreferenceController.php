<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PassPreference;
use App\Models\VaazCenter;
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
            ->with(['blockTypes' => function ($query) {
                $query->withCount('passPreferences');
            }])
            ->get();

        $summary = $vaazCenters->map(function ($vaazCenter) {
            return [
                'id' => $vaazCenter->id,
                'name' => $vaazCenter->name,
                'blocks' => $vaazCenter->blockTypes->map(function ($blockType) {
                    return [
                        'id' => $blockType->id,
                        'type' => $blockType->type,
                        'capacity' => $blockType->capacity,
                        'issued_passes' => $blockType->pass_preferences_count,
                        'availability' => $blockType->capacity - $blockType->pass_preferences_count,
                    ];
                }),
            ];
        });

        return response()->json($summary);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has('id')) {
            $passPreference = PassPreference::find($request->input('id'));
            if (!$passPreference) {
                return response()->json(['message' => 'Pass Preference not found'], 404);
            }
            return response()->json($passPreference);
        }

        if ($request->has('its_no')) {
            $passPreference = PassPreference::where('its_no', $request->input('its_no'))->first();
            if (!$passPreference) {
                return response()->json(['message' => 'Pass Preference not found for this ITS number'], 404);
            }
            return response()->json($passPreference);
        }

        return PassPreference::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_no' => 'required|integer|unique:pass_preferences,its_no',
            'block_id' => 'required|exists:block_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $passPreference = PassPreference::create($validator->validated());

        return response()->json($passPreference, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['message' => 'Pass Preference ID is required'], 400);
        }
        $passPreference = PassPreference::find($request->input('id'));
        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'its_no' => 'sometimes|required|integer|unique:pass_preferences,its_no,' . $passPreference->id,
            'block_id' => 'sometimes|required|exists:block_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $passPreference->update($validator->validated());

        return response()->json($passPreference);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['message' => 'Pass Preference ID is required'], 400);
        }
        $passPreference = PassPreference::find($request->input('id'));
        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found'], 404);
        }

        $passPreference->delete();

        return response()->json(null, 204);
    }
}
