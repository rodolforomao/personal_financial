<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::post('/auth/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages(['email' => ['Credenciais inválidas.']]);
    }

    return response()->json([
        'token' => $user->createToken('api')->plainTextToken,
        'user' => $user->load('workspaces'),
    ]);
});

Route::middleware('auth:sanctum')->get('/auth/me', fn (Request $request) => response()->json(
    $request->user()->load('workspaces')
));
