<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlockType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlockTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has('id')) {
            $blockType = BlockType::find($request->input('id'));
            if (!$blockType) {
                return response()->json(['message' => 'Block Type not found'], 404);
            }
            return response()->json($blockType);
        }

        if ($request->has('vaaz_center_id')) {
            $blockTypes = BlockType::where('vaaz_center_id', $request->input('vaaz_center_id'))->get();
            return response()->json($blockTypes);
        }

        return BlockType::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vaaz_center_id' => 'required|exists:vaaz_centers,id',
            'type' => 'required|string|max:255',
            'capacity' => 'required|integer',
            'min_age' => 'nullable|integer',
            'max_age' => 'nullable|integer',
            'gender' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $blockType = BlockType::create($validator->validated());

        return response()->json($blockType, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['message' => 'Block Type ID is required'], 400);
        }
        $blockType = BlockType::find($request->input('id'));
        if (!$blockType) {
            return response()->json(['message' => 'Block Type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'vaaz_center_id' => 'sometimes|required|exists:vaaz_centers,id',
            'type' => 'sometimes|required|string|max:255',
            'capacity' => 'sometimes|required|integer',
            'min_age' => 'nullable|integer',
            'max_age' => 'nullable|integer',
            'gender' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $blockType->update($validator->validated());

        return response()->json($blockType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['message' => 'Block Type ID is required'], 400);
        }
        $blockType = BlockType::find($request->input('id'));
        if (!$blockType) {
            return response()->json(['message' => 'Block Type not found'], 404);
        }

        $blockType->delete();

        return response()->json(null, 204);
    }
}
