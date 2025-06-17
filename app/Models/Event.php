<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PassPreference;
use App\Models\Miqaat;

/**
 * @OA\Schema(
 *     schema="Event",
 *     title="Event",
 *     description="Event model schema",
 *     @OA\Property(property="id", type="integer", format="int64", description="Primary key ID"),
 *     @OA\Property(property="miqaat_id", type="integer", description="ID of the Miqaat this event belongs to"),
 *     @OA\Property(property="name", type="string", description="Name of the event (e.g., 'Ashara Mubaraka 1445H - Colombo')"),
 *     @OA\Property(property="status", type="string", description="Status of the event (e.g., 'active', 'upcoming', 'past')"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Timestamp of creation"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Timestamp of last update"),
 *     @OA\Property(property="miqaat", type="object", ref="#/components/schemas/Miqaat", description="Associated Miqaat model"),
 *     @OA\Property(property="vaazCenters", type="array", @OA\Items(ref="#/components/schemas/VaazCenter"), nullable=true, description="List of Vaaz Centers for this event")
 * )
 */
class Event extends Model
{
    /**
     * Get the vaaz centers for the event.
     */
    public function vaazCenters()
    {
        return $this->hasMany(VaazCenter::class);
    }

        use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'miqaat_id',
        'name',
        'status',
    ];

    /**
     * Get the pass preferences for the event.
     */
    public function passPreferences()
    {
        return $this->hasMany(PassPreference::class);
    }

    /**
     * Get the miqaat that the event belongs to.
     */
    public function miqaat()
    {
        return $this->belongsTo(Miqaat::class);
    }
}
