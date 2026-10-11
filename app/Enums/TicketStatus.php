<?php

namespace App\Enums;

enum TicketStatus: string
{
    case PendingReview = 'pending_review';
    case Backlog = 'backlog';
    case InProgress = 'in_progress';
    case Testing = 'testing';
    case Done = 'done';
    case Delivered = 'delivered'; // done and handed over (archived / paid); counts as Done everywhere
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('enums.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingReview => '#8b5cf6',
            self::Backlog => '#64748b',
            self::InProgress => '#0ea5e9',
            self::Testing => '#f59e0b',
            self::Done => '#16a34a',
            self::Delivered => '#0d9488',
            self::Cancelled => '#94a3b8',
            self::Rejected => '#dc2626',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PendingReview => 'bi-hourglass-split',
            self::Backlog => 'bi-inboxes',
            self::InProgress => 'bi-play-circle',
            self::Testing => 'bi-bug',
            self::Done => 'bi-check2-circle',
            self::Delivered => 'bi-box-seam',
            self::Cancelled => 'bi-slash-circle',
            self::Rejected => 'bi-x-octagon',
        };
    }

    /** Statuses after which the ticket counts as closed. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Delivered, self::Cancelled, self::Rejected], true);
    }

    /** Finished work: Done or Delivered. Costs, bills, "done" counts and sprint progress use both. */
    public function isDone(): bool
    {
        return in_array($this, [self::Done, self::Delivered], true);
    }

    /** Values of the statuses that count as done (for whereIn). */
    public static function doneValues(): array
    {
        return [self::Done->value, self::Delivered->value];
    }
}
