<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';

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
