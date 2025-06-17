<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\VaazCenter;
use App\Models\PassPreference;

/**
 * @OA\Schema(
 *     schema="Block",
 *     title="Block",
 *     description="Block model schema for seating arrangements within a Vaaz Center",
 *     @OA\Property(property="id", type="integer", format="int64", description="Primary key ID"),
 *     @OA\Property(property="vaaz_center_id", type="integer", description="ID of the Vaaz Center this block belongs to"),
 *     @OA\Property(property="type", type="string", description="Type or name of the block (e.g., 'Section A', 'Balcony')"),
 *     @OA\Property(property="capacity", type="integer", nullable=true, description="Capacity of the block"),
 *     @OA\Property(property="min_age", type="integer", nullable=true, description="Minimum age for this block, if applicable"),
 *     @OA\Property(property="max_age", type="integer", nullable=true, description="Maximum age for this block, if applicable"),
 *     @OA\Property(property="gender", type="string", nullable=true, enum={"Male", "Female", "Any"}, description="Gender restriction for this block, if applicable"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Timestamp of creation"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Timestamp of last update"),
 *     @OA\Property(property="vaazCenter", type="object", ref="#/components/schemas/VaazCenter", description="Associated VaazCenter model")
 * )
 */
class Block extends Model
{
        use HasFactory;

    protected $table = 'blocks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vaaz_center_id',
        'type',
        'capacity',
        'min_age',
        'max_age',
        'gender',
    ];

    /**
     * Get the vaaz center that the block type belongs to.
     */
    public function vaazCenter()
    {
        return $this->belongsTo(VaazCenter::class);
    }

    /**
     * Get the pass preferences for the block type.
     */
    public function passPreferences()
    {
        return $this->hasMany(PassPreference::class, 'block_id');
    }
}
