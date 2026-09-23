<?php

namespace App\Enums;

enum TicketType: string
{
    case Task = 'task';
    case Bug = 'bug';
    case Feature = 'feature';
    case Improvement = 'improvement';
    case Support = 'support';
    case Question = 'question';

    public function label(): string
    {
        return __('enums.type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Task => 'bi-check2-square text-primary',
            self::Bug => 'bi-bug-fill text-danger',
            self::Feature => 'bi-stars text-success',
            self::Improvement => 'bi-arrow-up-circle text-info',
            self::Support => 'bi-life-preserver text-warning',
            self::Question => 'bi-question-circle text-secondary',
        };
    }
}
