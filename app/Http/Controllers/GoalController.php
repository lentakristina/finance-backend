<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index()
    {
        $goals = Goal::with('category')->where('user_id', auth()->id())->get();
        return response()->json($goals);
    }

    public function store(Request $request)
{
    $userId = auth()->id();
    if (!$userId) return response()->json(['message' => 'Unauthorized'], 401);

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'target_amount' => 'required|numeric|min:0.01',
        'category_id' => 'required|exists:categories,id'
    ]);

    $goal = Goal::create([
        'user_id' => $userId,
        'name' => $validated['name'],
        'target_amount' => $validated['target_amount'],
        'current_amount' => 0, 
        'category_id' => $validated['category_id']
    ]);

    return response()->json($goal, 201);
}

    public function update(Request $request, $id)
    {
        $goal = Goal::where('id', $id)->where('user_id', auth()->id())->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'target_amount' => 'sometimes|required|numeric|min:0',
            'category_id' => 'sometimes|required|exists:categories,id'
        ]);

        $goal->update($validated);
        return response()->json($goal);
    }

    public function destroy($id)
    {
        $goal = Goal::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $goal->delete();
        return response()->json(['message' => 'Goal deleted successfully']);
    }
}
