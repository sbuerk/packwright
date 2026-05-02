<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Extension with Invalid State',
    'description' => 'Has an unrecognised state value to exercise validator/normalizer fallbacks.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'totally_made_up_state',
    'author' => 'John Doe',
    'author_email' => 'john@example.com',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
    ],
];
