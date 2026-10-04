<?php

namespace App\Support;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which bills are paid. A payment is not tied to one bill (a customer may pay less or more,
 * or pay several bills at once), so the accepted payments of a customer pay their bills
 * from the oldest to the newest:
 *   payments 1,500 · bills 1,000 + 800 → first bill paid, second bill 500 of 800 (partly paid).
 */
class Bills
{
    public const STATUSES = ['unpaid', 'partial', 'paid'];

    /**
     * Paid amount of every (not deleted) bill of these customers.
     *
     * @param  int[]  $customerIds
     * @return Collection<int, int> bill id → paid amount
     */
    public static function allocate(array $customerIds): Collection
    {
        $customerIds = array_values(array_unique(array_filter($customerIds)));
        if (! $customerIds) {
            return collect();
        }

        $paid = Payment::where('status', Payment::ACCEPTED)->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')->selectRaw('customer_id, SUM(amount) as total')->pluck('total', 'customer_id');

        $out = collect();
        Bill::whereIn('customer_id', $customerIds)->orderBy('issued_on')->orderBy('id')
            ->get(['id', 'customer_id', 'total'])
            ->groupBy('customer_id')
            ->each(function (Collection $bills, $customerId) use ($paid, $out) {
                $left = (int) ($paid[$customerId] ?? 0);
                foreach ($bills as $bill) {
                    $share = min($bill->total, max(0, $left));
                    $out[$bill->id] = $share;
                    $left -= $share;
                }
            });

        return $out;
    }

    public static function status(int $total, int $paid): string
    {
        return $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    }

    /** What the customer still has to pay now: costs of done tickets − accepted payments (the transactions page "remaining"). */
    public static function debtOf(User $customer): int
    {
        return (int) Transactions::totals(Transactions::query([], $customer))['remaining'];
    }

    /** Status colours, the same in every page. */
    public static function color(string $status): string
    {
        return ['paid' => '#16a34a', 'partial' => '#ea7d24', 'unpaid' => '#e11d48'][$status] ?? '#64748b';
    }
}
