<?php

namespace App\Support;

use App\Models\GridPreference;

/**
 * A table with a column chooser. The visible columns are saved per user on the server.
 *
 *   $grid = Grid::make('tickets', ['number' => 'No.', 'title' => 'Title'], hidden: ['cost']);
 *   <th class="{{ $grid->cls('title') }}" data-col="title">
 */
class Grid
{
    private array $visible;

    /**
     * @param  array<string,string>  $columns  key => label
     * @param  string[]  $hidden  columns hidden by default
     * @param  string[]  $locked  columns that can never be hidden
     */
    public function __construct(
        public readonly string $key,
        public readonly array $columns,
        array $hidden = [],
        public readonly array $locked = [],
    ) {
        $saved = auth()->check()
            ? GridPreference::where('user_id', auth()->id())->where('grid_key', $key)->value('columns')
            : null;
        $saved = is_string($saved) ? json_decode($saved, true) : $saved;

        $this->visible = is_array($saved)
            ? array_values(array_intersect(array_keys($columns), array_merge($saved, $locked)))
            : array_values(array_diff(array_keys($columns), $hidden));
    }

    public static function make(string $key, array $columns, array $hidden = [], array $locked = []): self
    {
        return new self($key, $columns, $hidden, $locked);
    }

    public function visible(string $column): bool
    {
        return in_array($column, $this->visible, true);
    }

    /** CSS class for a <th>/<td>: hidden columns are rendered but not shown. */
    public function cls(string $column): string
    {
        return $this->visible($column) ? '' : 'd-none';
    }

    public function isLocked(string $column): bool
    {
        return in_array($column, $this->locked, true);
    }
}
