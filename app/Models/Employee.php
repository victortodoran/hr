<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Notifications\Invitation;
use Database\Factories\EmployeeFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int $department_id
 * @property bool $is_top
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'department_id'])] // @pest-mutate-ignore (class attributes never show as covered)
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])] // @pest-mutate-ignore (class attributes never show as covered)
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
            'email_verified_at' => 'datetime', // @pest-mutate-ignore (only read through hasVerifiedEmail, which works uncast)
            'password' => 'hashed',
            'is_top' => 'boolean', // @pest-mutate-ignore (PostgreSQL already returns a boolean)
            'two_factor_confirmed_at' => 'datetime', // @pest-mutate-ignore (starter-kit two-factor code)
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Email the Employee a link to set their password. Using it also
     * verifies their email.
     */
    public function sendInvitation(): void
    {
        $this->notify(new Invitation(Password::broker('invites')->createToken($this)));
    }

    /**
     * Whether any of the Employee's Roles grants the Permission.
     */
    public function holds(Permission $permission): bool
    {
        return $this->grantsOf($permission)->isNotEmpty();
    }

    /**
     * The widest Scope the Employee's Roles give them for a Permission about
     * Employees, or null when no Role grants it.
     */
    public function widestScopeFor(Permission $permission): ?Scope
    {
        return Scope::widest($this->grantsOf($permission)->pluck('scope')->filter());
    }

    /**
     * Every grant of the Permission across the Employee's Roles.
     *
     * @return Collection<int, RolePermission>
     */
    private function grantsOf(Permission $permission): Collection
    {
        return RolePermission::query()
            ->whereIn('role_id', $this->roles()->select('roles.id'))
            ->where('permission', $permission)
            ->get();
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
