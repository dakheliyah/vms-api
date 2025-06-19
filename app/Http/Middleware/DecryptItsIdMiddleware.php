<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DecryptItsIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Enforce that the 'Token' header is required for all routes using this middleware.
        if (!$request->hasHeader('Token')) {
            return response()->json(['message' => 'Token header is required.'], 400);
        }

        $encryptedId = $request->header('Token');

        if (empty($encryptedId)) {
            return response()->json(['message' => 'Token header cannot be empty.'], 400);
        }

        $decryptedId = $this->decrypt(urldecode($encryptedId));

        // If decryption fails, return an error response.
        if ($decryptedId === null) {
            return response()->json(['message' => 'The provided Token is invalid or corrupted.'], 422);
        }

        // Provide the decrypted ITS ID to the entire request lifecycle.
        // This makes it available via $request->input('its_id') in controllers.
        $request->merge(['user_decrypted_its_id' => $decryptedId]);

        // If a user is authenticated, also attach the ITS ID to the user object for convenience.
        if ($request->user()) {
            $request->user()->its_id = $decryptedId;
        }

        return $next($request);
    }

    /**
     * Decrypt data from secure storage using OpenSSL.
     * 
     * @param string $encrypted Base64 encoded encrypted string.
     * @param bool $json_decode Whether to JSON decode result if valid JSON.
     * @return mixed Decrypted data, or null on failure.
     */
    private function decrypt($encrypted, $json_decode = false)
    {
        if (empty($encrypted)) {
            return null;
        }
        
        $key = env('ITS_ENCRYPTION_KEY');
        
        $decoded = base64_decode($encrypted);
        if ($decoded === false) {
            error_log('[ITS OneLogin] Decryption failed: Invalid base64 encoding');
            return null;
        }
        
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        
        if (strlen($decoded) <= $ivLength) {
            error_log('[ITS OneLogin] Decryption failed: Data too short');
            return null;
        }
        
        $iv = substr($decoded, 0, $ivLength);
        $cipherText = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            error_log('[ITS OneLogin] Decryption failed: ' . openssl_error_string());
            return null;
        }
        
        if ($json_decode && !empty($decrypted) && ($decrypted[0] === '{' || $decrypted[0] === '[')) {
            $json_data = json_decode($decrypted, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
        }
        
        return $decrypted;
    }
}
