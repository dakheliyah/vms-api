<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Colombo Relay ITS API",
 *      description="API for managing ITS passes and related information for Colombo Relay.",
 *      @OA\Contact(
 *          email="admin@example.com"
 *      )
 * )
 * @OA\Schema(
 *     schema="Activity",
 *     title="Activity Log",
 *     description="Activity log entry model",
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="log_name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="subject_type", type="string"),
 *     @OA\Property(property="subject_id", type="integer"),
 *     @OA\Property(property="causer_type", type="string"),
 *     @OA\Property(property="causer_id", type="integer"),
 *     @OA\Property(property="properties", type="object", description="A JSON object containing the changed attributes"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="causer", type="object", description="The user who performed the action"),
 *     @OA\Property(property="subject", type="object", description="The model that was changed")
 * )
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
 * )
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      description="Enter token in format (Bearer <token>)"
 * )
 * @OA\Components(
 *     schemas={
 *         @OA\Schema(
 *             schema="StoreHizbeSaifeeGroupRequest",
 *             title="Store Hizbe Saifee Group Request",
 *             required={"name", "capacity", "group_no"},
 *             @OA\Property(property="name", type="string", description="Name of the group", example="Al-Ameen Group"),
 *             @OA\Property(property="capacity", type="integer", description="Capacity of the group", example=50),
 *             @OA\Property(property="group_no", type="integer", description="Unique group number", example=101),
 *             @OA\Property(property="whatsapp_link", type="string", nullable=true, description="WhatsApp group link", example="https://chat.whatsapp.com/samplelink")
 *         ),
 *         @OA\Schema(
 *             schema="UpdateHizbeSaifeeGroupRequest",
 *             title="Update Hizbe Saifee Group Request",
 *             required={"id"},
 *             @OA\Property(property="id", type="integer", format="int64", description="ID of the group to update"),
 *             @OA\Property(property="name", type="string", description="Name of the group", example="Al-Ameen Group Updated"),
 *             @OA\Property(property="capacity", type="integer", description="Capacity of the group", example=55),
 *             @OA\Property(property="group_no", type="integer", description="Unique group number", example=102),
 *             @OA\Property(property="whatsapp_link", type="string", nullable=true, description="WhatsApp group link", example="https://chat.whatsapp.com/newlink")
 *         ),
 *         @OA\Schema(
 *             schema="HizbeSaifeeGroupResponse",
 *             title="Hizbe Saifee Group Response",
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="data", ref="#/components/schemas/HizbeSaifeeGroup")
 *         )
 *     }
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
