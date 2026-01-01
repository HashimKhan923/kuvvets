<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ForkLift;
use App\Models\Location;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class ForkLiftController extends Controller
{
    public function index()
    {
        $forklifts = ForkLift::all();
        return response()->json(['forklifts' => $forklifts]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'required|string|unique:fork_lifts,serial_number',
            'purchase_date' => 'nullable|date',
            'status' => 'nullable|string|max:50',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $forklift = ForkLift::create([
            'name' => $request->name,
            'model' => $request->model,
            'serial_number' => $request->serial_number,
            'purchase_date' => $request->purchase_date,
            'status' => $request->status ?? 'available',
            'location_id' => $request->location_id,
        ]);

        return response()->json(['message' => 'Forklift created successfully', 'forklift' => $forklift], 201);
    }

    public function show($id)
    {
        $forklift = ForkLift::find($id);
        if (!$forklift) {
            return response()->json(['message' => 'Forklift not found'], 404);
        }
        return response()->json(['forklift' => $forklift]);
    }

    public function update(Request $request)
    {
        $forklift = ForkLift::find($request->id);
        if (!$forklift) {
            return response()->json(['message' => 'Forklift not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'model' => 'sometimes|nullable|string|max:255',
            'serial_number' => 'sometimes|required|string|unique:fork_lifts,serial_number,' . $request->id,
            'purchase_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|nullable|string|max:50',
            'location_id' => 'sometimes|nullable|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $forklift->update($request->all());

        return response()->json(['message' => 'Forklift updated successfully', 'forklift' => $forklift]);
    }

    public function destroy($id)
    {
        $forklift = ForkLift::find($id);
        if (!$forklift) {
            return response()->json(['message' => 'Forklift not found'], 404);
        }

        $forklift->delete();

        return response()->json(['message' => 'Forklift deleted successfully']);
    }


    
}
