<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'V13 Full Extension',
    'description' => 'A full TYPO3 13.4 era extension with no deprecated fields.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'John Doe',
    'author_email' => 'john@example.com',
    'author_company' => 'ACME Corp',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.3.99',
        ],
        'suggests' => [
            'news' => '',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'MyVendor\\MyExtV13\\' => 'Classes/',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'MyVendor\\MyExtV13\\Tests\\' => 'Tests/',
        ],
    ],
];
