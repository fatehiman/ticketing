<?php

return [
    'hello' => 'Hello :name,',
    'footer' => 'This email was sent automatically by Kimia Support System (:site). Please do not reply to it.',

    'reset' => [
        'subject' => 'Password recovery',
        'line1' => 'Someone asked to recover the password of your account in Kimia Support System (:site).',
        'line2' => 'Press the button below to choose a new password. This link is valid for :minutes minutes.',
        'button' => 'Choose a new password',
        'copy' => 'If the button does not work, open this link in your browser:',
        'ignore' => 'If you did not ask for this, ignore this email. Your password does not change.',
    ],

    'bill' => [
        'subject' => 'New bill no. :number — :project',
        'line1' => 'A new bill was issued for the project ":project". The details are below.',
        'debt' => 'Your total debt now (all bills)',
        'after_pay' => 'After you pay, register the voucher details in "Bills" or "Payment vouchers" in the system.',
        'button' => 'View the bill',
    ],
];
