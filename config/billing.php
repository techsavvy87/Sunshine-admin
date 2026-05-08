<?php

return [
    // Percentage value, e.g. 7 means 7%
    'state_tax_rate' => (float) env('BILLING_STATE_TAX_RATE', 7),

    // If schedule is delayed, allow this many minutes past the exact 24-hour mark.
    'precheckin_send_grace_minutes' => (int) env('PRECHECKIN_SEND_GRACE_MINUTES', 60),
];
