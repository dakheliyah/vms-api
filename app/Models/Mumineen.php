<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Mumineen",
 *     title="Mumineen",
 *     description="Mumineen model",
 *     @OA\Property(property="its_id", type="integer", description="Primary key ITS ID"),
 *     @OA\Property(property="hof_id", type="integer", nullable=true, description="Head of Family ITS ID"),
 *     @OA\Property(property="fullname", type="string", description="Full name"),
 *     @OA\Property(property="gender", type="string", enum={"male", "female", "other"}, description="Gender"),
 *     @OA\Property(property="age", type="integer", nullable=true, description="Age"),
 *     @OA\Property(property="mobile", type="string", nullable=true, description="Mobile number"),
 *     @OA\Property(property="country", type="string", nullable=true, description="Country"),
 *     @OA\Property(property="jamaat", type="string", nullable=true, description="Jamaat"),
 *     @OA\Property(property="idara", type="string", nullable=true, description="Idara"),
 *     @OA\Property(property="category", type="string", nullable=true, description="Category"),
 *     @OA\Property(property="prefix", type="string", nullable=true, description="Prefix"),
 *     @OA\Property(property="title", type="string", nullable=true, description="Title"),
 *     @OA\Property(property="venue_waaz", type="string", nullable=true, description="Waaz Venue"),
 *     @OA\Property(property="city", type="string", nullable=true, description="City"),
 *     @OA\Property(property="local_mehman", type="string", nullable=true, description="Local Mehman Status"),
 *     @OA\Property(property="arr_place_date", type="string", format="date", nullable=true, description="Arrival Place Date"),
 *     @OA\Property(property="flight_code", type="string", nullable=true, description="Flight Code"),
 *     @OA\Property(property="whatsapp_link_clicked", type="boolean", nullable=true, description="WhatsApp Link Clicked Status"),
 *     @OA\Property(property="daily_trans", type="string", nullable=true, description="Daily Transport"),
 *     @OA\Property(property="acc_arranged_at", type="string", nullable=true, description="Accommodation Arranged At"),
 *     @OA\Property(property="acc_zone", type="string", nullable=true, description="Accommodation Zone"),
 *     @OA\Property(property="hizbe_saifee_group_id", type="integer", nullable=true, description="ID of the assigned Hizbe Saifee Group"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp"),
 *     @OA\Property(property="hizbe_saifee_group", ref="#/components/schemas/HizbeSaifeeGroup", nullable=true, description="The assigned Hizbe Saifee Group")
 * )
 */
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
        'hizbe_saifee_group_id',
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
     * This can be filtered for specific events when needed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function passPreferences()
    {
        return $this->hasMany(PassPreference::class, 'its_id', 'its_id');
    }

    /**
     * Get the Hizbe Saifee group for the mumineen.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function hizbeSaifeeGroup()
    {
        return $this->belongsTo(HizbeSaifeeGroup::class, 'hizbe_saifee_group_id');
    }
}
