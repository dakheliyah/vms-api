<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User; // Assuming your User model is here
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for User Authentication"
 * )
 */
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
     * @OA\Post(
     *      path="/api/auth/login",
     *      operationId="loginUser",
     *      tags={"Authentication"},
     *      summary="Log in a user",
     *      description="Logs in a user with email and password, returns a JWT token.",
     *      @OA\RequestBody(
     *          required=true,
     *          description="User credentials",
     *          @OA\JsonContent(
     *              required={"email","password"},
     *              @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="password")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Login successful, token returned",
     *          @OA\JsonContent(
     *              @OA\Property(property="access_token", type="string", description="JWT access token"),
     *              @OA\Property(property="token_type", type="string", example="bearer"),
     *              @OA\Property(property="expires_in", type="integer", example=3600, description="Token expiry in seconds")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Invalid credentials",
     *          @OA\JsonContent(type="object", example={"error": "invalid_credentials"})
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Could not create token",
     *          @OA\JsonContent(type="object", example={"error": "could_not_create_token"})
     *      )
     * )
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
     * @OA\Post(
     *      path="/api/auth/me",
     *      operationId="getAuthenticatedUser",
     *      tags={"Authentication"},
     *      summary="Get authenticated user details",
     *      description="Returns details of the currently authenticated user.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/User")
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated or Token absent/invalid",
     *          @OA\JsonContent(type="object", example={"error": "token_absent_or_invalid"})
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(type="object", example={"error": "user_not_found"})
     *      )
     * )
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
     * @OA\Post(
     *      path="/api/auth/logout",
     *      operationId="logoutUser",
     *      tags={"Authentication"},
     *      summary="Log out a user",
     *      description="Invalidates the current user's JWT token.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successfully logged out",
     *          @OA\JsonContent(type="object", example={"message": "Successfully logged out"})
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Could not invalidate token",
     *          @OA\JsonContent(type="object", example={"error": "could_not_invalidate_token"})
     *      )
     * )
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
     * @OA\Post(
     *      path="/api/auth/refresh",
     *      operationId="refreshToken",
     *      tags={"Authentication"},
     *      summary="Refresh a JWT token",
     *      description="Refreshes an expired JWT token, returns a new token.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Token refreshed successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="access_token", type="string", description="New JWT access token"),
     *              @OA\Property(property="token_type", type="string", example="bearer"),
     *              @OA\Property(property="expires_in", type="integer", example=3600, description="Token expiry in seconds")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Token cannot be refreshed (e.g., blacklisted)",
     *          @OA\JsonContent(type="object", example={"error": "could_not_refresh_token"})
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Could not refresh token (general error)",
     *          @OA\JsonContent(type="object", example={"error": "could_not_refresh_token"})
     *      )
     * )
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
    /**
     * @OA\Schema(
     *   schema="User",
     *   title="User",
     *   description="User model",
     *   @OA\Property(property="id", type="integer", format="int64", description="User ID"),
     *   @OA\Property(property="name", type="string", description="User's name"),
     *   @OA\Property(property="email", type="string", format="email", description="User's email address"),
     *   @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, description="Timestamp of email verification"),
     *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, description="Creation timestamp"),
     *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, description="Last update timestamp")
     * )
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
