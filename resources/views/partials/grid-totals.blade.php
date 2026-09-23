{{-- Totals row of a grid (Grid::totals). Sums are for all filtered records, not only this page.
     Shown only while a column with a total is visible; app.js toggles it when columns change. --}}
@if ($grid->hasTotals())
    @php($first = array_key_first($grid->columns))
    <tfoot class="grid-totals {{ $grid->showTotals() ? '' : 'd-none' }}">
    <tr>
        @foreach ($grid->columns as $key => $label)
            <td data-col="{{ $key }}" @if ($grid->hasTotal($key)) data-total @endif class="{{ $grid->cls($key) }} text-nowrap">
                @if ($grid->hasTotal($key))
                    {{ $grid->total($key) }}
                @elseif ($key === $first)
                    <i class="bi bi-calculator"></i> {{ __('app.grand_total') }}
                @endif
            </td>
        @endforeach
    </tr>
    </tfoot>
@endif
