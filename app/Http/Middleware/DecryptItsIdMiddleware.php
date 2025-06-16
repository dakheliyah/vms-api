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
        // Check if its_id parameter exists and has a value.
        if ($request->filled('its_id') || $request->route('its_id')) {
            $encryptedId = $request->route('its_id') ?? $request->input('its_id');

            if ($encryptedId) {
                $decryptedId = $this->decrypt($encryptedId);

                // If decryption fails, the custom decrypt returns null.
                // We should return a proper error response instead of letting it crash.
                if ($decryptedId === null) {
                    return response()->json(['message' => 'The provided ITS ID is invalid or corrupted.'], 422);
                }

                // Update the request with the decrypted value.
                if ($request->route('its_id')) {
                    $request->route()->setParameter('its_id', $decryptedId);
                } else {
                    $request->merge(['its_id' => $decryptedId]);
                }
            }
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
