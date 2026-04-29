<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Extension with Deprecated Keys',
    'description' => 'Has all deprecated fields; reader must preserve them.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'author' => 'John Doe',
    'author_email' => 'john@example.com',
    'author_company' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'clearCacheOnLoad' => true,
    'uploadfolder' => false,
    'createDirs' => 'uploads/my_ext',
];
