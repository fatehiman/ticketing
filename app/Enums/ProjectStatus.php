<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return __('enums.project_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnHold => 'warning',
            self::Completed => 'info',
            self::Archived => 'secondary',
        };
    }
}
