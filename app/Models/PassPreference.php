<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Block;

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
        'block_id',
    ];

    /**
     * Get the block type that the pass preference belongs to.
     */
    public function block()
    {
        return $this->belongsTo(Block::class, 'block_id');
    }
}
