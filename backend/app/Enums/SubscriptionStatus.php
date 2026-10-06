<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';

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
