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
        // Check if its_id parameter exists in the request
        if ($request->has('its_id') || $request->route('its_id')) {
            // Get the its_id from route parameters or query string
            $encryptedId = $request->route('its_id') ?? $request->input('its_id');
            
            // Only process if its_id is not empty
            if (!empty($encryptedId)) {
                // Decrypt the its_id
                $decryptedId = $this->decrypt($encryptedId);
                
                // Update the request parameter with the decrypted value
                if ($decryptedId !== null) {
                    if ($request->route('its_id')) {
                        $request->route()->setParameter('its_id', $decryptedId);
                    } else {
                        $request->merge(['its_id' => $decryptedId]);
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Decrypt data from secure storage
     * 
     * @param string $encrypted Base64 encoded encrypted string
     * @param bool $json_decode Whether to JSON decode result if valid JSON
     * @return mixed Decrypted data, potentially JSON decoded if requested
     */
    private function decrypt($encrypted, $json_decode = false) {
        // Handle empty input
        if (empty($encrypted)) {
            return null;
        }
        
        // Get encryption key from environment
        $key = env('ITS_ENCRYPTION_KEY'); // Make sure to add this to your .env file
        
        // Decode base64 string
        $decoded = base64_decode($encrypted);
        if ($decoded === false) {
            error_log('[ITS OneLogin] Decryption failed: Invalid base64 encoding');
            return null;
        }
        
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        
        // Check if the string is long enough to contain IV
        if (strlen($decoded) <= $ivLength) {
            error_log('[ITS OneLogin] Decryption failed: Data too short');
            return null;
        }
        
        // Extract IV and ciphertext
        $iv = substr($decoded, 0, $ivLength);
        $cipherText = substr($decoded, $ivLength);

        // Decrypt the data
        $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            error_log('[ITS OneLogin] Decryption failed: ' . openssl_error_string());
            return null;
        }
        
        // If JSON decode requested and result looks like JSON, attempt to decode
        if ($json_decode && !empty($decrypted) && ($decrypted[0] === '{' || $decrypted[0] === '[')) {
            $json_data = json_decode($decrypted, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json_data;
            }
        }
        
        return $decrypted;
    }
}
