<?php

namespace App\Enums;

enum NotificationType: string
{
    case NewEntry = 'new_entry';
    case Expiry = 'expiry';
    case Subscription = 'subscription';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
