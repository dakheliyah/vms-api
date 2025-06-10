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
        'eits_id',
        'hof_its_id',
        'full_name',
        'gender',
        'age',
        'mobile',
        'country',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'its_id' => 'integer',
        'eits_id' => 'integer',
        'hof_its_id' => 'integer',
        'age' => 'integer',
    ];
}
