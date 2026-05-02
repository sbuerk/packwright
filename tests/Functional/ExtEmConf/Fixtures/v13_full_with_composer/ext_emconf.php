<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'V13 Full With Composer',
    'description' => 'A v13 fixture with a sibling composer.json.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'Jane Doe',
    'author_email' => 'jane@example.com',
    'author_company' => 'ACME Corp',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
    ],
];
