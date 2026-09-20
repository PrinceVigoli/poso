<?php

return [
    'offense_categories' => ['Traffic Violation', 'Peace and Order', 'Public Safety', 'Other Local Ordinance'],
    'allow_shared_receipts' => true,
    'citizen_domain' => env('CITIZEN_DOMAIN', 'portal.poso.test'),
    // Violations, excluding dismissed ones, before a person is flagged as a
    // repeat offender. One value so the list, the profile and the dashboard
    // cannot drift apart. The office sets the number; there is no time window
    // until they approve one.
    'repeat_offender_threshold' => 3,
    'confiscated_ids' => ['None', "Driver's License", 'National ID', 'Student ID', 'Company ID', 'Other'],
];
