<?php

return [
    'title' => 'Transactions',
    'subtitle_staff' => 'Customer payments and costs of done tickets',
    'subtitle_customer' => 'Your payments and the costs of done tickets in your projects',
    'new_payment' => 'Add payment',
    'edit_payment' => 'Edit payment',
    'delete_payment' => 'Delete this payment',
    'payment_saved' => 'Payment saved.',
    'choose_customer' => '— Choose a customer —',
    'all_customers' => 'All customers',
    'no_project' => 'No project',
    'amount_hint' => 'Whole number, no decimal point.',
    'cost_rule' => 'A ticket cost is shown when the ticket is Done and has a cost. Its date is the due date, or the day it was done.',
    'total_payments' => 'Total payments',
    'total_costs' => 'Total costs',
    'remaining' => 'Remaining',
    'remaining_hint' => 'Remaining = total costs − total payments. A positive number is still to be paid; a negative number is a prepayment. All totals are for every filtered record, not only this page.',
    'by_project' => 'Summary by project',

    'kinds' => [
        'payment' => 'Payment',
        'cost' => 'Ticket cost',
    ],

    'filter' => [
        'keyword' => 'Description or ticket title…',
        'date' => 'Date',
        'amount' => 'Amount',
    ],

    'fields' => [
        'date' => 'Date',
        'kind' => 'Type',
        'customer' => 'Customer',
        'project' => 'Project',
        'description' => 'Description',
        'amount' => 'Amount',
        'payment' => 'Payment',
        'cost' => 'Cost',
    ],
];
