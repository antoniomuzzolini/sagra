<?php

namespace App\Enums;

/**
 * The vertical modules that can be enabled per event (D21). The kernel
 * (people, events, areas, roles, notifications) is never a module and can't
 * be switched off.
 */
enum Module: string
{
    case Shifts = 'shifts';
    case Supplies = 'supplies';
    case Orders = 'orders';

    /**
     * The modules a brand new event starts with: Fieste is a shift manager
     * first, the rest is opt-in.
     *
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return [self::Shifts->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Shifts => 'Turni',
            self::Supplies => 'Forniture',
            // Cassa and comande are two faces of the same order (D21).
            self::Orders => 'Ordini e cassa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Shifts => 'Programmazione dei turni e prenotazione dei volontari.',
            self::Supplies => 'Fornitori, acquisti, noleggi e prestiti, con le fatture.',
            self::Orders => 'Cassa con listino e schermi comande per la cucina.',
        };
    }
}
