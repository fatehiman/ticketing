<?php

namespace App\Enums;

/** Priority levels, with the classic Jira colours and icons. */
enum TicketPriority: string
{
    case Highest = 'highest';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Lowest = 'lowest';

    public function label(): string
    {
        return __('enums.priority.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Highest => '#CE0000',
            self::High => '#EA4444',
            self::Medium => '#EA7D24',
            self::Low => '#2A8735',
            self::Lowest => '#55A557',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Highest => 'bi-chevron-double-up',
            self::High => 'bi-chevron-up',
            self::Medium => 'bi-list',
            self::Low => 'bi-chevron-down',
            self::Lowest => 'bi-chevron-double-down',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Highest => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Lowest => 1,
        };
    }
}
