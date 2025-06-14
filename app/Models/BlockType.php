<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\VaazCenter;
use App\Models\PassPreference;

class BlockType extends Model
{
        use HasFactory;

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
        return $this->hasMany(PassPreference::class);
    }
}
