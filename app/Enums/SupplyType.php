<?php

namespace App\Enums;

/**
 * How a supply was obtained (Forniture module): bought, rented or borrowed.
 */
enum SupplyType: string
{
    case Purchase = 'purchase';
    case Rental = 'rental';
    case Loan = 'loan';
}
