<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class AuthorizationHelper
{
    /**
     * Check if the authenticated user is an admin.
     *
     * @param Request $request
     * @return boolean
     */
    public static function isAdmin(Request $request): bool
    {
        // TODO: Move this to a configuration file or a database table for better management.
        $adminItsIds = [
            "30361114", // ITS53
            "40456337", // ITS53
            "30361286", // ITS53
            "30362306", // Hamza bhai Ajmer
            "30359366", // Huzefa bhai Frutti
            "20323929", // Ibrahim Bs
            "30362765"  // Huzefa bhai Murtaza
        ];

        $userItsId = $request->input('user_decrypted_its_id');

        if (!$userItsId) {
            return false;
        }

        return in_array($userItsId, $adminItsIds);
    }
}
