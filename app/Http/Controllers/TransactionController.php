<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Support\Grid;
use App\Support\ProjectContext;
use App\Support\Transactions;
use Illuminate\Http\Request;

/**
 * Transactions: customer payments next to the costs of done tickets.
 * Developers add payments here; admins see all transactions read-only, customers their own.
 * Totals are for all filtered records, not only the current page.
 * Like every page, it follows the project picked in the top bar.
 */
class TransactionController extends Controller
{
    public function index(Request $request, ProjectContext $context)
    {
        $user = $request->user();
        $scope = $context->scopeIds();
        $filters = Transactions::normalize($request->query(), $user, $scope);
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true) ? (int) $request->query('per_page') : 25;

        $query = Transactions::query($filters, $user, $scope);

        $columns = [
            'date' => __('transactions.fields.date'),
            'kind' => __('transactions.fields.kind'),
            'customer' => __('transactions.fields.customer'),
            'project' => __('transactions.fields.project'),
            'description' => __('transactions.fields.description'),
            'payment' => __('transactions.fields.payment'),
            'cost' => __('transactions.fields.cost'),
            'actions' => __('app.actions'),
        ];
        if (! $user->isStaff()) {
            unset($columns['customer']); // customers see only their own rows
        }
        if (! $user->isDeveloper()) {
            unset($columns['actions']); // customers and admins only read
        }
        $grid = Grid::make('transactions', $columns, locked: ['date']);
        $grid->totals($query, [
            'payment' => [Transactions::sumOf('payment'), Grid::MONEY],
            'cost' => [Transactions::sumOf('cost'), Grid::MONEY],
        ]);

        $rows = Transactions::applySort(clone $query, $request->query('sort'), $request->query('dir'))
            ->paginate($perPage)->withQueryString();

        // Names for the rows on this page.
        $customers = User::withTrashed()->whereIn('id', $rows->pluck('customer_id')->filter()->unique())->get()->keyBy('id');
        $projects = Project::withTrashed()->whereIn('id', $rows->pluck('project_id')->filter()->unique())->get()->keyBy('id');
        $editable = $user->isDeveloper()
            ? Payment::whereIn('id', $rows->where('kind', 'payment')->pluck('id'))->get()
                ->filter(fn ($p) => $user->can('update', $p))->pluck('id')->flip()
            : collect();

        $byProject = Transactions::byProject($query);

        return view('transactions.index', [
            'rows' => $rows,
            'filters' => $filters,
            'grid' => $grid,
            'perPage' => $perPage,
            'sort' => $request->query('sort', 'tx_date'),
            'dir' => $request->query('dir', 'desc'),
            'totals' => Transactions::totals($query),
            // A second summary table when the result covers more than one project.
            'byProject' => count($byProject) > 1 ? $byProject : [],
            'customers' => $customers,
            'rowProjects' => $projects,
            'editable' => $editable,
            'customerOptions' => $user->isStaff() ? $this->customerOptions($user, $scope) : collect(),
            'currentProject' => $context->current(),
            'projectOptions' => Project::query()->visibleTo($user)
                ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->orderBy('name')->get(),
        ]);
    }

    /** Customers for the filter lists; only the customers of the top-bar project when one is picked. */
    public static function customerOptions(User $user, ?array $scope = null)
    {
        return User::query()->customersOf($user)
            ->when($scope !== null, fn ($q) => $q->customersInProjects($scope))
            ->with('projects:id')->orderBy('first_name')->orderBy('last_name')->get();
    }
}
