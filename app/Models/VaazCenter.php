<?php

namespace App\Models;

use App\Models\Event;
use App\Models\Block;
use App\Models\PassPreference;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="VaazCenter",
 *     title="Vaaz Center",
 *     description="Vaaz Center model schema",
 *     @OA\Property(property="id", type="integer", format="int64", description="Primary key ID"),
 *     @OA\Property(property="name", type="string", description="Name of the Vaaz Center"),
 *     @OA\Property(property="event_id", type="integer", description="ID of the Event this center belongs to"),
 *     @OA\Property(property="est_capacity", type="integer", nullable=true, description="Estimated capacity of the center"),
 *     @OA\Property(property="lat", type="number", format="float", nullable=true, description="Latitude of the center"),
 *     @OA\Property(property="long", type="number", format="float", nullable=true, description="Longitude of the center"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Timestamp of creation"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Timestamp of last update"),
 *     @OA\Property(property="event", type="object", ref="#/components/schemas/Event", description="Associated Event model"),
 *     @OA\Property(property="blocks", type="array", @OA\Items(ref="#/components/schemas/Block"), nullable=true, description="List of blocks within this center")
 * )
 */
class VaazCenter extends Model
{
    /**
     * Get the event that the vaaz center belongs to.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

        use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'est_capacity',
        'male_capacity',
        'female_capacity',
        'lat',
        'long',
        'event_id',
    ];

    /**
     * Get the block types for the vaaz center.
     */
    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    /**
     * Get the pass preferences associated with the vaaz center.
     */
    public function passPreferences()
    {
        return $this->hasMany(PassPreference::class);
    }
}
