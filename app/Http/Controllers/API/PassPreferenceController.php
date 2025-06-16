<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PassPreference;
use App\Models\VaazCenter;
use App\Models\Block;
use App\Models\Event; // Added Event model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Enums\PassType;

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
            ->withCount('passPreferences') // Count passes directly associated with the VaazCenter
            ->with(['blocks' => function ($query) {
                $query->withCount('passPreferences'); // Count passes for each block
            }])
            ->get();

        $summary = $vaazCenters->map(function ($vaazCenter) {
            $vaazCenterIssuedPasses = $vaazCenter->pass_preferences_count ?? 0;
            // If est_capacity is 0 or null, consider it unlimited for availability calculation
            $vaazCenterCapacity = $vaazCenter->est_capacity ?? 0;
            $vaazCenterAvailability = ($vaazCenterCapacity > 0) ? ($vaazCenterCapacity - $vaazCenterIssuedPasses) : 'unlimited';

            return [
                'id' => $vaazCenter->id,
                'name' => $vaazCenter->name,
                'vaaz_center_capacity' => $vaazCenterCapacity,
                'vaaz_center_issued_passes' => $vaazCenterIssuedPasses,
                'vaaz_center_availability' => $vaazCenterAvailability,
                'blocks' => $vaazCenter->blocks->map(function ($block) {
                    $blockIssuedPasses = $block->pass_preferences_count ?? 0;
                    // If block capacity is 0 or null, consider it unlimited
                    $blockCapacity = $block->capacity ?? 0;
                    $blockAvailability = ($blockCapacity > 0) ? ($blockCapacity - $blockIssuedPasses) : 'unlimited';
                    return [
                        'id' => $block->id,
                        'type' => $block->type,
                        'capacity' => $blockCapacity,
                        'gender' => $block->gender,
                        'min_age' => $block->min_age,
                        'max_age' => $block->max_age,
                        'block_issued_passes' => $blockIssuedPasses,
                        'block_availability' => $blockAvailability,
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
            $passPreference = PassPreference::where('its_id', $request->input('its_id'))->with(['block', 'vaazCenter', 'event'])->first(); 
            if (!$passPreference) {
                return response()->json(['message' => 'Pass Preference not found for this ITS number'], 404);
            }
            return response()->json($passPreference);
        }

        // Consider pagination for `all()` if the list can grow large
        return PassPreference::with(['block', 'vaazCenter', 'event'])->get(); 
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|unique:pass_preferences,its_id',
            'event_id' => 'required|integer|exists:events,id',
            'pass_type' => ['nullable', Rule::enum(PassType::class)],
            'block_id' => 'sometimes|nullable|integer|exists:blocks,id',
            'vaaz_center_id' => 'sometimes|nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();

        // The event_id from validatedData is guaranteed to exist by the 'exists:events,id' validation rule.
        $targetEventIdForComparison = $validatedData['event_id']; 

        // Cross-reference Vaaz Center with the target Event ID
        if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
            $vaazCenter = VaazCenter::find($validatedData['vaaz_center_id']);
            // $vaazCenter will be null if not found; 'exists' validation for vaaz_center_id covers this.
            // We proceed if $vaazCenter is found and its event_id does not match.
            if ($vaazCenter && $vaazCenter->event_id != $targetEventIdForComparison) {
                return response()->json(['message' => 'The selected Vaaz Center does not belong to the specified event.'], 422);
            }
        }

        // Cross-reference Block (if provided)
        if (array_key_exists('block_id', $validatedData) && $validatedData['block_id'] !== null) {
            $block = Block::find($validatedData['block_id']); // $block is guaranteed by 'exists' rule if ID is valid

            // Block must exist and be associated with a VaazCenter for further checks.
            if (!$block || !$block->vaazCenter) {
                return response()->json(['message' => 'Selected Block is invalid or not properly associated with a Vaaz Center.'], 422);
            }

            // 1. Check: Block's own VaazCenter must belong to the target Event.
            if ($block->vaazCenter->event_id != $targetEventIdForComparison) {
                return response()->json(['message' => 'The selected Block (via its Vaaz Center) does not belong to the specified event.'], 422);
            }

            // 2. Check: If a vaaz_center_id was ALSO explicitly provided in the request for the PassPreference (and is not null),
            //    ensure this Block belongs to THAT specific VaazCenter.
            if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
                if ($block->vaaz_center_id != $validatedData['vaaz_center_id']) {
                    return response()->json(['message' => 'The selected Block does not belong to the specified Vaaz Center.'], 422);
                }
            }
        }

        // Vaaz Center Capacity Check (only if vaaz_center_id is provided and not null)
        if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
            $vaazCenter = VaazCenter::find($validatedData['vaaz_center_id']);
            // 'exists' validation handles if $vaazCenter is null, but defensive check is fine.
            if ($vaazCenter) { 
                $currentPassesInCenter = PassPreference::where('vaaz_center_id', $vaazCenter->id)->count();
                if ($vaazCenter->est_capacity > 0 && $currentPassesInCenter >= $vaazCenter->est_capacity) {
                    return response()->json(['message' => 'Selected Vaaz Center is full. Cannot issue more passes.'], 422);
                }
            }
        }

        // Block Capacity Check (only if block_id is provided and not null)
        if (array_key_exists('block_id', $validatedData) && $validatedData['block_id'] !== null) {
            $block = Block::find($validatedData['block_id']);
            // 'exists' validation handles if $block is null, but defensive check is fine.
            if ($block) {
                $currentPassesCount = PassPreference::where('block_id', $block->id)->count();
                // Ensure block capacity is a positive number before checking
                if ($block->capacity > 0 && $currentPassesCount >= $block->capacity) {
                    return response()->json(['message' => 'Selected block is full. Cannot issue more passes.'], 422);
                }
            }
        }

        // Eloquent will handle missing keys by inserting NULL if the DB column is nullable.
        $passPreference = PassPreference::create($validatedData);

        return response()->json($passPreference->load(['block', 'vaazCenter']), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'its_id' => 'required|integer|exists:pass_preferences,its_id',
            'event_id' => 'sometimes|required|integer|exists:events,id', // Required if present
            'pass_type' => ['sometimes', 'nullable', Rule::enum(PassType::class)],
            'block_id' => 'sometimes|nullable|integer|exists:blocks,id',
            'vaaz_center_id' => 'sometimes|nullable|integer|exists:vaaz_centers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validatedData = $validator->validated();
        $passPreference = PassPreference::where('its_id', $validatedData['its_id'])->first();

        if (!$passPreference) {
            return response()->json(['message' => 'Pass Preference not found for the given ITS number.'], 404);
        }

        // Determine the target event_id for cross-referencing
        $targetEventId = array_key_exists('event_id', $validatedData) ? $validatedData['event_id'] : $passPreference->event_id;
        $targetEvent = Event::find($targetEventId);

        if (!$targetEvent) {
            // This should ideally be caught by 'exists:events,id' validation if event_id is being updated
            // Or implies an issue if we are using an existing passPreference's event_id that's somehow invalid.
            return response()->json(['message' => 'Target event not found for cross-referencing.'], 422); 
        }

        // Cross-reference Vaaz Center with the target Event
        if (array_key_exists('vaaz_center_id', $validatedData) && $validatedData['vaaz_center_id'] !== null) {
            $vaazCenter = VaazCenter::find($validatedData['vaaz_center_id']);
            if ($vaazCenter && $vaazCenter->event_id != $targetEvent->id) {
                return response()->json(['message' => 'The selected Vaaz Center does not belong to the target event.'], 422);
            }
        }

        // Cross-reference Block with the target Event (via Block's VaazCenter)
        if (array_key_exists('block_id', $validatedData) && $validatedData['block_id'] !== null) {
            $block = Block::find($validatedData['block_id']);
            // Ensure block exists and is associated with a VaazCenter that belongs to the target event
            if ($block && (!$block->vaazCenter || $block->vaazCenter->event_id != $targetEvent->id)) {
                return response()->json(['message' => 'The selected Block does not belong to the target event.'], 422);
            }
        }

        // Vaaz Center Capacity Check (if center is being changed or set)
        if (array_key_exists('vaaz_center_id', $validatedData)) {
            $newVaazCenterId = $validatedData['vaaz_center_id'];
            if ($passPreference->vaaz_center_id != $newVaazCenterId && $newVaazCenterId !== null) {
                $newVaazCenter = VaazCenter::find($newVaazCenterId);
                if ($newVaazCenter) {
                    $currentPassesInNewCenter = PassPreference::where('vaaz_center_id', $newVaazCenter->id)->count();
                    if ($newVaazCenter->est_capacity > 0 && $currentPassesInNewCenter >= $newVaazCenter->est_capacity) {
                        return response()->json(['message' => 'New selected Vaaz Center is full. Cannot move pass.'], 422);
                    }
                }
            }
        }

        // If block_id is being changed, check capacity of the new block
        if (array_key_exists('block_id', $validatedData)) {
            $newBlockId = $validatedData['block_id'];
            if ($passPreference->block_id != $newBlockId && $newBlockId !== null) {
                $newBlock = Block::find($newBlockId);
                if (!$newBlock) {
                    return response()->json(['message' => 'New selected block not found.'], 404);
                }

                $currentPassesInNewBlock = PassPreference::where('block_id', $newBlock->id)->count();
                if ($currentPassesInNewBlock >= $newBlock->capacity) {
                    return response()->json(['message' => 'New selected block is full. Cannot move pass.'], 422);
                }
            }
        }

        // Update fields if they are present in the validated data
        if (array_key_exists('block_id', $validatedData)) {
            $passPreference->block_id = $validatedData['block_id'];
        }
        if (array_key_exists('vaaz_center_id', $validatedData)) {
            $passPreference->vaaz_center_id = $validatedData['vaaz_center_id'];
        }
        if (array_key_exists('event_id', $validatedData)) { // Add event_id update
            $passPreference->event_id = $validatedData['event_id'];
        }
        
        $passPreference->save();

        return response()->json($passPreference->load(['block', 'vaazCenter']));
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
