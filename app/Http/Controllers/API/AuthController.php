<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User; // Assuming your User model is here

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Apply auth:api middleware to all methods in this controller
        // except for the login method.
        // Note: 'jwt.auth' is an old alias, 'auth:api' is correct if 'api' guard uses 'jwt' driver.
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_create_token', 'message' => $e->getMessage()], 500);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (! $user) {
                return response()->json(['error' => 'user_not_found'], 404);
            }
            return response()->json($user);
        } catch (JWTException $e) {
            return response()->json(['error' => 'token_absent_or_invalid', 'message' => $e->getMessage()], 401);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Successfully logged out']);
        } catch (JWTException $e) {
            // Something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'could_not_invalidate_token', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return $this->respondWithToken($newToken);
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_refresh_token', 'message' => $e->getMessage()], $e instanceof \Tymon\JWTAuth\Exceptions\TokenBlacklistedException ? 401 : 500);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60, // TTL in seconds
            'user' => JWTAuth::user() // Get user details via JWTAuth facade
        ]);
    }
}
