<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Minimal Extension',
    'description' => 'The minimal valid ext_emconf.php with only required fields.',
    'version' => '0.1.0',
    'category' => 'misc',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
