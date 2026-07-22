<?php

namespace App\Enums;

/**
 * Scoped roles held in `person_roles`. The tenant-wide organizer
 * capability is not here — it's a flag on the person (D19).
 */
enum Role: string
{
    case AreaManager = 'area_manager';
}
