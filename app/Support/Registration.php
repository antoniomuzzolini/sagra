<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Whether new associations may sign up (D22). Anyone able to create a tenant
 * could reach the accounts of the existing ones (docs/review/03-roadmap.md
 * §1.1), so sign-up closes as soon as one association exists. A brand new
 * install has none yet and stays open for its first organizer;
 * REGISTRATION_ENABLED reopens it on purpose.
 */
class Registration
{
    public static function isOpen(): bool
    {
        return config('fieste.registration_enabled') || Tenant::query()->doesntExist();
    }
}
