<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'V12 Full Extension',
    'description' => 'A full TYPO3 12.4 era extension with clearCacheOnLoad still present.',
    'version' => '3.1.0',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'John Doe',
    'author_email' => 'john@example.com',
    'author_company' => 'ACME Corp',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
            'php' => '8.1.0-8.2.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'MyVendor\\MyExtV12\\' => 'Classes/',
        ],
    ],
    'clearCacheOnLoad' => false,
];
