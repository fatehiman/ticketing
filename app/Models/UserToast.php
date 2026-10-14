<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A toast that waits for a user (see App\Support\Toast). */
class UserToast extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
