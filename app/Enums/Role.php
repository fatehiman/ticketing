<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Developer = 'developer';
    case Customer = 'customer';

    public function label(): string
    {
        return __('enums.role.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Developer => 'primary',
            self::Customer => 'success',
        };
    }
}
