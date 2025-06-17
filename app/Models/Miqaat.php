<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Event;

/**
 * @OA\Schema(
 *     schema="Miqaat",
 *     title="Miqaat",
 *     description="Miqaat model schema (e.g., a religious occasion or event series)",
 *     @OA\Property(property="id", type="integer", format="int64", description="Primary key ID"),
 *     @OA\Property(property="name", type="string", description="Name of the Miqaat (e.g., 'Ashara Mubaraka 1445H')"),
 *     @OA\Property(property="status", type="string", description="Status of the Miqaat (e.g., 'active', 'upcoming', 'past')"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Timestamp of creation"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Timestamp of last update"),
 *     @OA\Property(property="events", type="array", @OA\Items(ref="#/components/schemas/Event"), nullable=true, description="List of events associated with this Miqaat")
 * )
 */
class Miqaat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'status',
    ];

    /**
     * Get the events for the miqaat.
     */
    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
