<?php

namespace App\Enums;

enum SignupStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case Declined = 'declined';
}
