<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Constraints Typo Extension',
    'description' => 'Uses singular typo keys for all three constraint groups.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'depend' => [
            'php' => '8.2.0-8.3.99',
            'broken' => 'not-a-valid-version',
        ],
        'suggest' => [
            'news' => '',
        ],
        'conflict' => [
            'old_ext' => '1.0.0',
        ],
    ],
];
