<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'V8 Full Extension',
    'description' => 'A full TYPO3 8.7 era extension with all known fields.',
    'version' => '1.2.3',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'John Doe',
    'author_email' => 'john@example.com',
    'author_company' => 'ACME Corp',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-8.7.99',
            'php' => '7.0.0-7.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'MyVendor\\MyExt\\' => 'Classes/',
        ],
    ],
    'clearCacheOnLoad' => false,
    'uploadfolder' => false,
    'createDirs' => '',
];
