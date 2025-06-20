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
            "30361114",
            "40456337",
            "30361286",
            "30362306",
            "30359366",
            
        ];

        $userItsId = $request->input('user_decrypted_its_id');

        if (!$userItsId) {
            return false;
        }

        return in_array($userItsId, $adminItsIds);
    }
}
