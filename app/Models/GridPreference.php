<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GridPreference extends Model
{
    protected $fillable = ['user_id', 'grid_key', 'columns'];

    protected function casts(): array
    {
        return ['columns' => 'array'];
    }
}
