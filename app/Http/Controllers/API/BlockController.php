<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has('id')) {
            $block = Block::find($request->input('id'));
            if (!$block) {
                return response()->json(['message' => 'Block not found'], 404);
            }
            return response()->json($block);
        }

        if ($request->has('vaaz_center_id')) {
            $blocks = Block::where('vaaz_center_id', $request->input('vaaz_center_id'))->get();
            return response()->json($blocks);
        }

        return Block::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vaaz_center_id' => 'required|exists:vaaz_centers,id',
            'type' => 'required|string|max:255', // Assuming 'type' is a required field for a block
            'capacity' => 'required|integer|min:0', // Assuming 'capacity' is required
            'min_age' => 'nullable|integer',
            'max_age' => 'nullable|integer',
            'gender' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $block = Block::create($validator->validated());

        return response()->json($block, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['message' => 'Block ID is required'], 400);
        }
        $block = Block::find($request->input('id'));
        if (!$block) {
            return response()->json(['message' => 'Block not found'], 404);
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

        $block->update($validator->validated());

        return response()->json($block);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['message' => 'Block ID is required'], 400);
        }
        $block = Block::find($request->input('id'));
        if (!$block) {
            return response()->json(['message' => 'Block not found'], 404);
        }

        $block->delete();

        return response()->json(null, 204);
    }
}
