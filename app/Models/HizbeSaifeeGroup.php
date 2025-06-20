<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="HizbeSaifeeGroup",
 *     title="Hizbe Saifee Group",
 *     description="Hizbe Saifee Group model",
 *     @OA\Property(property="id", type="integer", format="int64", description="ID"),
 *     @OA\Property(property="name", type="string", description="Name of the group"),
 *     @OA\Property(property="capacity", type="integer", description="Capacity of the group"),
 *     @OA\Property(property="group_no", type="integer", description="Unique group number"),
 *     @OA\Property(property="whatsapp_link", type="string", nullable=true, description="WhatsApp group link"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
class HizbeSaifeeGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capacity',
        'group_no',
        'whatsapp_link',
    ];

    /**
     * Get the mumineen belonging to this Hizbe Saifee group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function mumineen()
    {
        return $this->hasMany(Mumineen::class, 'hizbe_saifee_group_id');
    }
}
