<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public sign-up of new associations
    |--------------------------------------------------------------------------
    |
    | Off by default (D22): during the pilot, associations are let in by
    | invitation. A fresh install with no association yet stays open anyway,
    | so its first organizer can register (see App\Support\Registration).
    |
    */

    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', false),

];
