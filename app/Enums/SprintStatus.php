<?php

namespace App\Enums;

enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return __('enums.sprint_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'secondary',
            self::Active => 'success',
            self::Closed => 'dark',
        };
    }
}
