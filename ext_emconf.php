<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "gridelements".
 * Auto generated 17-06-2013 22:35
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Grid Elements',
    'description' => 'Be part of the future of TYPO3! Support Gridelements now and unlock exclusive early access to Version 13! The well-established Gridelements Version 12 elevates TYPO3 by bringing grid-based layouts to content elements, with powerful features like advanced drag & drop and real references. Supercharge your backend workflow and make daily tasks easier. Join us in creating the next exciting version: https://coders.care/for/crowdfunding/gridelements',
    'category' => 'be',
    'version' => '12.0.0',
    'state' => 'stable',
    'createDirs' => '',
    'modify_tables' => 'tt_content',
    'author' => 'Grid Elements Team',
    'author_email' => 'info@cybercraft.de',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.99-12.4.99',
        ],
        'conflicts' => [
            'templavoila' => '',
            'jfmulticontent' => ''
        ],
        'suggests' => [],
    ],
];
