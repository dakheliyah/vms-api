<?php

namespace App\Models\Swagger;

/**
 * @OA\Schema(
 *     schema="Mumineen",
 *     title="Mumineen",
 *     description="Mumineen model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="its_id", type="string", example="ITS123456"),
 *     @OA\Property(property="eits_id", type="string", example="EITS123456"),
 *     @OA\Property(property="hof_its_id", type="string", example="HOF123456"),
 *     @OA\Property(property="full_name", type="string", example="John Doe"),
 *     @OA\Property(property="gender", type="string", example="male", enum={"male", "female", "other"}),
 *     @OA\Property(property="age", type="integer", example=30),
 *     @OA\Property(property="mobile", type="string", example="+1234567890"),
 *     @OA\Property(property="country", type="string", example="United States"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-06-10T16:30:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-06-10T16:30:00.000000Z")
 * )
 */
class Mumineen
{
    // This is a dummy class to hold Swagger annotations
}
