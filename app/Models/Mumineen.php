<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mumineen extends Model
{
    use HasFactory;
    
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'its_id';
    
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'its_id',
        'hof_id', // Renamed from hof_its_id
        'fullname', // Renamed from full_name
        'gender',
        'age',
        'jamaat',
        'idara',
        'category',
        'prefix',
        'title',
        'venue_waaz',
        'city',
        'local_mehman',
        'arr_place_date',
        'flight_code',
        'whatsapp_link_clicked',
        'daily_trans',
        'acc_arranged_at',
        'acc_zone',
        'mobile', // Existing field, moved to maintain some logical grouping
        'country', // Existing field
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'its_id' => 'integer',
        'eits_id' => 'integer',
        'hof_id' => 'integer',
        'age' => 'integer',
    ];
    
    /**
     * Get the pass preferences for the mumineen.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function passPreferences()
    {
        return $this->hasMany(PassPreference::class, 'its_id', 'its_id');
    }
    
    /**
     * Get a single pass preference for the mumineen for a specific event.
     * This returns a hasOne relationship for cases where we only want one preference per event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function passPreference()
    {
        return $this->hasOne(PassPreference::class, 'its_id', 'its_id');
    }
}
