<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One SMS (or voice call) in the outbox, with its result. */
class SmsMessage extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = [
        'mobile', 'method', 'template_id', 'params', 'code', 'purpose', 'status',
        'attempts', 'reference_id', 'last_error', 'sent_at',
    ];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'code' => 'encrypted',
            'sent_at' => 'datetime',
        ];
    }
}
