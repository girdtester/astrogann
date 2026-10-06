<?php

namespace App\Enums;

enum DeviceType: string
{
    case Web = 'web';
    case Mobile = 'mobile';

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
