<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'name',
        'status',
    ];
}
