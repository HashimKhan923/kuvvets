<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForkLift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'model',
        'serial_number',
        'purchase_date',
        'status',
        'qr_code',
        'location_id',
    ];


    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    
}
