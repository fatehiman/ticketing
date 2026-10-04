<?php

/*
| msgway template IDs by meaning. Templates are approved in the msgway panel.
| [paramN] values are sent in order; [code] is sent as a separate field.
*/
return [
    'templates' => [
        'otp' => 3,                      // کد تایید شما: [code]
        'bill_issued' => 24562,          // [param1] = bill number
        'ticket_to_developer' => 24564,  // [param1] = project name, [param2] = ticket number
        'reply_to_customer' => 24561,    // [param1] = ticket number
    ],

    // Retry waits (seconds) after a failed send. OTP codes are useless after 2 minutes: one retry only.
    'backoff' => [60, 300, 600, 1800],
    'otp_backoff' => [60],
];
