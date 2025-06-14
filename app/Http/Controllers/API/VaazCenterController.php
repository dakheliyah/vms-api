<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VaazCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VaazCenterController extends Controller
{
    /**
     * Display a listing of the resource or a single resource.
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'est_capacity' => 'required|integer',
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
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $vaazCenter = VaazCenter::find($request->input('id'));
        if (!$vaazCenter) {
            return response()->json(['message' => 'Vaaz Center not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'est_capacity' => 'sometimes|required|integer',
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
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $vaazCenter = VaazCenter::find($request->input('id'));
        if (!$vaazCenter) {
            return response()->json(['message' => 'Vaaz Center not found'], 404);
        }

        $vaazCenter->delete();

        return response()->json(null, 204);
    }
}
