<?php

namespace App\Support;

use App\Models\GridPreference;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * A table with a column chooser. The visible columns are saved per user on the server.
 *
 *   $grid = Grid::make('tickets', ['number' => 'No.', 'title' => 'Title'], hidden: ['cost']);
 *   <th class="{{ $grid->cls('title') }}" data-col="title">
 *
 * Optional totals row: sums over ALL filtered records (not only the current page).
 *
 *   $grid->totals($query, ['cost' => ['cost', 'money'], 'logged_time' => ['logged_minutes', 'duration']]);
 *   @include('partials.grid-totals', ['grid' => $grid])
 *
 * The row is shown only while at least one total column is visible.
 */
class Grid
{
    public const MONEY = 'money';

    public const DURATION = 'duration';

    public const NUMBER = 'number';

    private array $visible;

    /** @var array<string,string> column => formatted total */
    private array $totals = [];

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

    /**
     * Compute the totals row with one aggregate query over the whole filtered query.
     *
     * @param  array<string,array{0:string,1:string}>  $defs  column => [db column or SQL aggregate, format]
     *                                                        A plain column name is wrapped in SUM().
     */
    public function totals(BuilderContract $query, array $defs): self
    {
        $base = clone $query;
        if (method_exists($base, 'withoutEagerLoads')) {
            $base = $base->withoutEagerLoads();
        }
        $base = method_exists($base, 'toBase') ? $base->toBase() : $base;
        $base->orders = null;
        $base->limit = $base->offset = null;

        $selects = [];
        foreach ($defs as $column => [$expr]) {
            $sql = preg_match('/^\w+$/', $expr) ? "SUM({$expr})" : $expr;
            $selects[] = "{$sql} as ".$base->getGrammar()->wrap('t_'.$column);
        }
        // select() also drops earlier select bindings (e.g. from withCount()).
        $row = (array) $base->select(DB::raw(implode(', ', $selects)))->first();

        foreach ($defs as $column => [, $format]) {
            $value = $row['t_'.$column] ?? null;
            $this->totals[$column] = match ($format) {
                self::MONEY => Money::format($value ?? 0),
                self::DURATION => Duration::format((int) $value),
                default => number_format((float) $value),
            };
        }

        return $this;
    }

    public function hasTotals(): bool
    {
        return $this->totals !== [];
    }

    public function hasTotal(string $column): bool
    {
        return array_key_exists($column, $this->totals);
    }

    public function total(string $column): string
    {
        return $this->totals[$column] ?? '';
    }

    /** True when at least one column with a total is visible (then the totals row is shown). */
    public function showTotals(): bool
    {
        foreach (array_keys($this->totals) as $column) {
            if ($this->visible($column)) {
                return true;
            }
        }

        return false;
    }
}
