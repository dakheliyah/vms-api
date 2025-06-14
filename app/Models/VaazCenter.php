<?php

namespace App\Models;

use App\Models\Event;
use App\Models\BlockType;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'lat',
        'long',
        'event_id',
    ];

    /**
     * Get the block types for the vaaz center.
     */
    public function blockTypes()
    {
        return $this->hasMany(BlockType::class);
    }
}
