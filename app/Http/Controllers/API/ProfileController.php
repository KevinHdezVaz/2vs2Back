<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show()
    {
        return auth()->user()->profile;
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'position' => 'nullable|string',
            'skill_level' => 'nullable|integer|min:1|max:10',
            'preferred_zone' => 'required|string',
            'playing_schedule' => 'nullable|array'
        ]);

        $profile = auth()->user()->profile;
        $profile->update($validated);

        return response()->json($profile);
    }
}