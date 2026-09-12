<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password', 'role_id', 'age', 'image'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

       # IDENTIFICADOR QUE SE GUARDA EN EL TOKEN (sub)
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    # CLAIMS PERSONALIZADOS: VACIO A PROPOSITO
    # No se agregan datos sensibles al payload porque el JWT no cifra,
    # solo firma: cualquiera puede leer su contenido.
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    # RELACION CON ROLES
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    # RELACION CON ACCOUNT
    public function account()
    {
        return $this->hasOne(Account::class);
    }
}
