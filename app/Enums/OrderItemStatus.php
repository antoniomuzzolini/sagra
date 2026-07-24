<?php

namespace App\Enums;

/**
 * Kitchen state of an order line (Ordini/Cassa slice B — comande/KDS):
 * queued at the till, ready to serve, handed over.
 */
enum OrderItemStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Served = 'served';
}
