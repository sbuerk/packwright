<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'V9 Full Extension',
    'description' => 'A full TYPO3 9.5 era extension with deprecated v8 fields still present.',
    'version' => '2.0.0',
    'category' => 'be',
    'state' => 'stable',
    'author' => 'Jane Doe',
    'author_email' => 'jane@example.com',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.4.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'MyVendor\\MyExtV9\\' => 'Classes/',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'MyVendor\\MyExtV9\\Tests\\' => 'Tests/',
        ],
    ],
    'clearCacheOnLoad' => false,
    'uploadfolder' => false,
    'createDirs' => '',
];
