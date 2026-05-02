<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Nested test_extension under one/package-2',
    'description' => 'Deep fixture: must NOT be matched by */*/ext_emconf.php.',
    'version' => '0.1.0',
    'category' => 'misc',
    'state' => 'beta',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
    ],
];
