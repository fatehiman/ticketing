<?php

namespace App\Support;

use App\Enums\TicketStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * The transactions ledger: customer payments + costs of done tickets, in one query.
 *
 * A ticket is a "cost" row only while it is done AND has a cost. Its date is the due date,
 * or the day it was done when it has no due date (TX_DATE_COST).
 * Costs are read live from the tickets table (no copy is stored), so changing the
 * status, removing the cost or deleting the ticket removes the row and its
 * amount from every total right away.
 *
 * Costs belong to customers by project: a customer's costs are the costs of their projects.
 */
class Transactions
{
    public const KINDS = ['payment', 'cost'];

    public const KEYS = ['customer_id', 'project', 'kind', 'date_from', 'date_to', 'amount_from', 'amount_to', 'q'];

    public const ARRAY_KEYS = ['project', 'kind'];

    public const SORTS = ['tx_date', 'amount'];

    /** Date of a cost row: due date, else the day the ticket was done (old rows: last update). */
    private const TX_DATE_COST = 'COALESCE(tickets.due_date, DATE(tickets.resolved_at), DATE(tickets.updated_at))';

    /** Keep only known keys and drop empty values. */
    public static function normalize(array $input, User $user): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $value = $input[$key] ?? null;
            if (in_array($key, self::ARRAY_KEYS, true)) {
                $value = array_values(array_unique(array_filter(array_map('strval', (array) $value), fn ($v) => $v !== '')));
                sort($value);
                if ($value) {
                    $out[$key] = $value;
                }
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $out[$key] = trim((string) $value);
            }
        }
        foreach (['amount_from', 'amount_to'] as $key) {
            if (isset($out[$key])) {
                $out[$key] = Money::parse($out[$key]);
                if (! ctype_digit((string) $out[$key])) {
                    unset($out[$key]);
                }
            }
        }
        if (! $user->isStaff()) {
            unset($out['customer_id']); // a customer always sees only their own transactions
        }

        return $out;
    }

    /**
     * The union of both row kinds as a plain query:
     * kind, id, customer_id, project_id, amount, tx_date, description, ticket_number.
     */
    public static function query(array $f, User $user): Builder
    {
        $kinds = $f['kind'] ?? self::KINDS;
        $projectIds = collect($f['project'] ?? [])->reject(fn ($p) => $p === 'none')->map(fn ($p) => (int) $p)->values()->all();
        $withoutProject = in_array('none', $f['project'] ?? [], true);
        $projectFilter = isset($f['project']);

        // One customer: a customer sees only themself; staff may filter by one of their customers.
        // Payments follow the customer; costs follow the customer's projects.
        $customerId = $customerProjectIds = null;
        if ($user->isCustomer()) {
            $customerId = $user->id; // their costs are already limited to their projects by visibleTo()
        } elseif (isset($f['customer_id'])) {
            $customer = User::query()->customersOf($user)->whereKey((int) $f['customer_id'])->first();
            $customerId = $customer->id ?? 0;
            $customerProjectIds = $customer ? $customer->projects()->pluck('projects.id')->all() : [];
        }

        // --- payments ---------------------------------------------------------------
        $payments = Payment::query()->visibleTo($user)
            ->when($customerId !== null, fn ($q) => $q->where('payments.customer_id', $customerId))
            ->when($projectFilter, fn ($q) => $q->where(function ($w) use ($projectIds, $withoutProject) {
                $w->whereIn('payments.project_id', $projectIds ?: [0]);
                if ($withoutProject) {
                    $w->orWhereNull('payments.project_id');
                }
            }))
            ->when(isset($f['q']), fn ($q) => $q->where('payments.description', 'like', '%'.$f['q'].'%'))
            ->toBase()
            ->select([
                DB::raw("'payment' as kind"), 'payments.id', 'payments.customer_id', 'payments.project_id',
                'payments.amount', 'payments.paid_on as tx_date', 'payments.description', DB::raw('NULL as ticket_number'),
            ]);
        self::range($payments, 'payments.paid_on', 'payments.amount', $f);

        // --- ticket costs -----------------------------------------------------------
        $costs = Ticket::query()->visibleTo($user)
            ->where('tickets.status', TicketStatus::Done->value)
            ->where('tickets.cost', '>', 0)
            ->when($customerProjectIds !== null, fn ($q) => $q->whereIn('tickets.project_id', $customerProjectIds ?: [0]))
            ->when($projectFilter, fn ($q) => $q->whereIn('tickets.project_id', $projectIds ?: [0]))
            ->when(isset($f['q']), function ($q) use ($f) {
                $q->where(function ($w) use ($f) {
                    $w->where('tickets.title', 'like', '%'.$f['q'].'%');
                    $digits = Dates::latinDigits(ltrim($f['q'], '#'));
                    if (ctype_digit($digits)) {
                        $w->orWhere('tickets.number', (int) $digits);
                    }
                });
            })
            ->toBase()
            ->select([
                DB::raw("'cost' as kind"), 'tickets.id', DB::raw('NULL as customer_id'), 'tickets.project_id',
                'tickets.cost as amount', DB::raw(self::TX_DATE_COST.' as tx_date'), 'tickets.title as description', 'tickets.number as ticket_number',
            ]);
        self::range($costs, DB::raw(self::TX_DATE_COST), 'tickets.cost', $f);

        if (! in_array('payment', $kinds, true)) {
            $payments->whereRaw('1 = 0');
        }
        if (! in_array('cost', $kinds, true)) {
            $costs->whereRaw('1 = 0');
        }

        return DB::query()->fromSub($payments->unionAll($costs), 'tx');
    }

    public static function applySort(Builder $query, ?string $sort, ?string $dir): Builder
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'tx_date';

        return $query->orderBy($sort, $dir === 'asc' ? 'asc' : 'desc')->orderBy('kind')->orderByDesc('id');
    }

    /** SQL aggregates used by the grid totals and the summaries. */
    public static function sumOf(string $kind): string
    {
        return "SUM(CASE WHEN kind = '{$kind}' THEN amount ELSE 0 END)";
    }

    /** Total payments, total costs and what remains (costs − payments) for the filtered rows. */
    public static function totals(Builder $query): array
    {
        $row = (clone $query)->select(DB::raw(self::sumOf('payment').' as payments, '.self::sumOf('cost').' as costs'))->first();

        return self::withRemaining((float) ($row->payments ?? 0), (float) ($row->costs ?? 0));
    }

    /** The same totals per project (project = null for payments without a project). */
    public static function byProject(Builder $query): array
    {
        $rows = (clone $query)
            ->select('project_id', DB::raw(self::sumOf('payment').' as payments, '.self::sumOf('cost').' as costs'))
            ->groupBy('project_id')->get();

        $projects = Project::withTrashed()->whereIn('id', $rows->pluck('project_id')->filter())->get()->keyBy('id');

        return $rows->map(fn ($r) => ['project' => $projects->get($r->project_id)]
            + self::withRemaining((float) $r->payments, (float) $r->costs))
            ->sortBy(fn ($r) => $r['project']?->name ?? "\u{FFFF}")->values()->all();
    }

    private static function withRemaining(float $payments, float $costs): array
    {
        return ['payments' => $payments, 'costs' => $costs, 'remaining' => $costs - $payments];
    }

    private static function range(Builder $query, string|Expression $dateColumn, string $amountColumn, array $f): void
    {
        if ($from = Dates::parse($f['date_from'] ?? null)) {
            $query->where($dateColumn, '>=', $from->toDateString());
        }
        if ($to = Dates::parse($f['date_to'] ?? null)) {
            $query->where($dateColumn, '<=', $to->toDateString());
        }
        if (isset($f['amount_from'])) {
            $query->where($amountColumn, '>=', (int) $f['amount_from']);
        }
        if (isset($f['amount_to'])) {
            $query->where($amountColumn, '<=', (int) $f['amount_to']);
        }
    }
}
