<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movement extends Model
{
    use HasFactory;

    # TABLA A LA QUE HACEMOS REFERENCIA
    protected $table = 'movements';

    # CAMPOS DE ASIGNACION MASIVA
    protected $fillable = [
        'account_id',
        'type',
        'amount',
        'counterpart_cbu',
    ];

    # RELACION CON ACCOUNT 
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
