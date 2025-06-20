<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Block;
use App\Models\VaazCenter;
use App\Models\Event;
use App\Enums\PassType;

/**
 * @OA\Schema(
 *     schema="PassPreference",
 *     title="Pass Preference",
 *     description="Pass Preference model schema",
 *     @OA\Property(property="id", type="integer", format="int64", description="Primary key ID"),
 *     @OA\Property(property="its_id", type="integer", description="ITS ID of the mumineen"),
 *     @OA\Property(property="event_id", type="integer", description="ID of the associated event"),
 *     @OA\Property(property="pass_type", type="string", enum={"RAHAT", "CHAIR", "GENERAL", "MUM_WITH_KIDS"}, description="Type of pass allocated"),
 *     @OA\Property(property="block_id", type="integer", nullable=true, description="ID of the assigned block, if any"),
 *     @OA\Property(property="vaaz_center_id", type="integer", nullable=true, description="ID of the assigned Vaaz center, if any"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Timestamp of creation"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Timestamp of last update"),
 *     @OA\Property(property="block", type="object", nullable=true, ref="#/components/schemas/Block", description="Associated Block model"),
 *     @OA\Property(property="vaazCenter", type="object", nullable=true, ref="#/components/schemas/VaazCenter", description="Associated VaazCenter model"),
 *     @OA\Property(property="event", type="object", ref="#/components/schemas/Event", description="Associated Event model")
 * )
 */
class PassPreference extends Model
{
        use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'its_id',
        'event_id',
        'pass_type',
        'block_id',
        'vaaz_center_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'pass_type' => PassType::class,
    ];

    /**
     * Get the block type that the pass preference belongs to.
     */
    public function block()
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    /**
     * Get the Vaaz center that the pass preference belongs to.
     */
    public function vaazCenter()
    {
        return $this->belongsTo(VaazCenter::class, 'vaaz_center_id');
    }

    /**
     * Get the event that the pass preference belongs to.
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get the mumineen that owns the pass preference.
     */
    public function mumineen()
    {
        return $this->belongsTo(Mumineen::class, 'its_id', 'its_id');
    }
}
