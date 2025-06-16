<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Block;
use App\Models\VaazCenter;
use App\Models\Event;
use App\Enums\PassType;

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
}
