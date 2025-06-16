<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Miqaat;
use Illuminate\Support\Facades\Validator;

class MiqaatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Miqaat::with('events')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:miqaats,name',
            'status' => 'sometimes|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $miqaat = Miqaat::create($validator->validated());

        return response()->json($miqaat, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $miqaat = Miqaat::with('events')->find($id);

        if (!$miqaat) {
            return response()->json(['message' => 'Miqaat not found'], 404);
        }

        return response()->json($miqaat);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $miqaat = Miqaat::find($id);

        if (!$miqaat) {
            return response()->json(['message' => 'Miqaat not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:miqaats,name,' . $id,
            'status' => 'sometimes|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $miqaat->update($validator->validated());

        return response()->json($miqaat);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $miqaat = Miqaat::find($id);

        if (!$miqaat) {
            return response()->json(['message' => 'Miqaat not found'], 404);
        }

        $miqaat->delete();

        return response()->json(null, 204);
    }
}
