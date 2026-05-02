<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Constraints Typo Collision Extension',
    'description' => 'Has the same extension key in both typo and canonical constraints groups.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.3.99',
        ],
        'depend' => [
            'typo3' => '12.4.0-12.4.99',
            'fluid_styled_content' => '13.4.0-13.4.99',
        ],
    ],
];
