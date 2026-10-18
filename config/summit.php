<?php

return [
    'timezone' => 'Africa/Lagos',
    'referral_options' => ['Church announcement', 'Facebook', 'Flyer', 'Someone invited me', 'WhatsApp', 'Instagram', 'Other', 'Outreach', 'Impact Cell Group', 'Evangelism', 'Billboard', 'Email', 'TV'],
    'roles' => [
        'super_admin' => 'Super Admin',
        'info_com' => 'Info Com Team',
        'follow_up_admin' => 'Follow-Up Admin',
        'data_entry' => 'Follow-Up Data Entry',
        'officer' => 'Follow-Up Officer',
    ],
    'permissions' => [
        'super_admin' => ['platform.manage', 'info.access', 'info.attendee.create', 'info.attendance.mark', 'info.import', 'info.report.view', 'guest.access', 'guest.create', 'guest.assign', 'guest.contact', 'guest.report.view', 'guest.import'],
        'info_com' => ['info.access', 'info.attendee.create', 'info.attendance.mark', 'info.report.view'],
        'follow_up_admin' => ['guest.access', 'guest.create', 'guest.assign', 'guest.contact', 'guest.report.view'],
        'data_entry' => ['guest.access', 'guest.create'],
        'officer' => ['guest.access', 'guest.contact'],
    ],
];
