<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class Employee extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the Employee's passkeys.
     *
     * laravel/passkeys stores the owner in a column named user_id, so the
     * foreign key is named explicitly rather than guessed from this model.
     *
     * @return HasMany<Passkey, Model>
     */
    public function passkeys(): HasMany
    {
        return self::passkeysOwnedBy($this);
    }

    /**
     * Build the passkeys relation for an owner typed as a plain Model, which
     * is the relation type the PasskeyUser contract declares.
     *
     * @return HasMany<Passkey, Model>
     */
    private static function passkeysOwnedBy(Model $owner): HasMany
    {
        return $owner->hasMany(Passkeys::passkeyModel(), 'user_id');
    }
}
