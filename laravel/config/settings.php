<?php

return [
    'require_diocese_transfer_approval' => env('REQUIRE_DIOCESE_TRANSFER_APPROVAL', true),
    'certificate_diocese_approval_required' => [
        'membership' => true,
        'recommendation' => true,
        'no_objection' => true,
        'baptism' => false,
        'marriage' => false,
        'death' => false,
        'custom' => false,
        'course_completion' => false,
    ],
];
