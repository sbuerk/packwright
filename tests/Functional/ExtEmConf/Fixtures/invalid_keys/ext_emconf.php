<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Extension with Unknown Keys',
    'description' => 'Has unknown keys and an unrecognised state value.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'John Doe',
    'author_email' => 'not-a-valid-email',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'unknown_custom_key' => 'some value',
    'another_unknown' => 42,
];
