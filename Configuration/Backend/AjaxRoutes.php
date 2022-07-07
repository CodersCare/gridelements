<?php

declare(strict_types=1);

use GridElementsTeam\Gridelements\Backend;

/**
 * Definitions for routes provided by EXT:backend
 * Contains all AJAX-based routes for entry points
 *
 * Currently the "access" property is only used so no token creation + validation is made
 * but will be extended further.
 */
return [

    // Get summary of records to localize
    'records_localize_summary' => [
        'path' => '/records/localize/summary',
        'target' => Backend\LocalizationController::class . '::getRecordLocalizeSummary',
    ],

];
