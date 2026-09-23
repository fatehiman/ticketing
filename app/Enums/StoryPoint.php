<?php

namespace App\Enums;

/** Fibonacci story points with plain-language labels. */
enum StoryPoint: int
{
    case Tiny = 1;
    case VerySmall = 2;
    case Small = 3;
    case Medium = 5;
    case Large = 8;
    case VeryLarge = 13;
    case Huge = 21;

    public function label(): string
    {
        return __('enums.story_point.'.$this->value);
    }

    /** "5 — Medium (a few days)". */
    public function fullLabel(): string
    {
        return $this->value.' — '.$this->label();
    }

    public static function labelFor(?int $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value)?->fullLabel() ?? (string) $value;
    }
}
