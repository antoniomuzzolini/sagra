<?php

namespace App\Support;

use App\Models\Person;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Credentials are global, the registry is per tenant (D25). The same email
 * may sit on people of several associations, so a lookup by email alone
 * takes "the first row": on PostgreSQL whichever the heap returns first,
 * possibly in another association (docs/review/03-roadmap.md §1.1). Every
 * lookup and check of an email as a credential goes through here.
 */
class Credentials
{
    public const EMAIL_TAKEN = 'Questa email è già usata su Fieste.';

    /**
     * Login only considers people holding a password, the oldest first.
     */
    public static function forLogin(): Closure
    {
        return fn (Builder $query) => $query->whereNotNull('password')->orderBy('id');
    }

    /**
     * Forgot password and reset prefer the person holding a password without
     * excluding the others: an account invite sets the first password.
     */
    public static function forReset(): Closure
    {
        return fn (Builder $query) => $query
            ->orderByRaw('case when password is null then 1 else 0 end')
            ->orderBy('id');
    }

    /**
     * Whether a person of another tenant has this email, with or without a
     * password. An email only becomes a credential if it is nowhere else:
     * set-password tokens are keyed by the email alone, so a token pending
     * for one person could otherwise reset the password of another.
     */
    public static function emailUsedElsewhere(string $email, int $tenantId): bool
    {
        return Person::query()
            ->where('email', $email)
            ->where('tenant_id', '<>', $tenantId)
            ->exists();
    }

    /**
     * Validation rule for the forms that change an existing person's email:
     * someone holding a password can't move to an email used in another
     * tenant, as for an account invite. Only a change is checked, so a
     * namesake that appeared elsewhere afterwards doesn't stop people from
     * editing the rest of their details.
     */
    public static function emailRule(?Person $person): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($person) {
            if ($person?->password !== null
                && is_string($value)
                && $value !== $person->email
                && static::emailUsedElsewhere($value, $person->tenant_id)) {
                $fail(static::EMAIL_TAKEN);
            }
        };
    }
}
