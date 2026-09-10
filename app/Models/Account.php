<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    # TABLA A LA QUE HACEMOS REFERENCIA
    protected $table = 'accounts';

    # CAMPOS DE ASIGNACION MASIVA
    protected $fillable = [
        'user_id',
        'cbu',
    ];

    # RELACION CON USUARIO
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    #RELACION CON MOVEMENT
    public function movements()
    {
        return $this->hasMany(Movement::class);
    }
}
