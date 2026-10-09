<?php

namespace App\Enums;

enum NotificationType: string
{
    case NewEntry = 'new_entry';
    case Expiry = 'expiry';
    case Subscription = 'subscription';
    case Announcement = 'announcement'; // D-100: written by the Super Admin

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
