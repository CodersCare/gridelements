<?php

namespace GridElementsTeam\Gridelements\Xclass;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Script Class for the Web > List module; rendering the listing of records on a page
 * Add custom JavaScript for gridelements
 */
class RecordListController extends \TYPO3\CMS\Recordlist\Controller\RecordListController
{

    /**
     * Injects the request object for the current request or subrequest
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Gridelements/GridElementsOnReady');
        return parent::mainAction($request);
    }
}
