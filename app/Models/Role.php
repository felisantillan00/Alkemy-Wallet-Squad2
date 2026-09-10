<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    # TABLA A LA QUE HACEMOS REFERENCIA
    protected $table = 'roles';

    # ESPECIFICAMOS QUE CAMPOS SON DE ASIGNACION MASIVA
    protected $fillable = [
        'role_name',
        'role_description'
    ];

    # RELACION CON USUARIOS
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
