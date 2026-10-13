<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Middleware;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Backend\LayoutWizardIcon;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Registers the grid layout wizard icon requested from the icon AJAX route, see LayoutWizardIcon
 */
final class RegisterLayoutWizardIcon implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');
        if ($route instanceof Route && $route->getOption('_identifier') === 'ajax_icons') {
            $this->registerRequestedIcon($request);
        }
        return $handler->handle($request);
    }

    private function registerRequestedIcon(ServerRequestInterface $request): void
    {
        // read like IconController does
        $requestedIcon = json_decode((string)($request->getParsedBody()['icon'] ?? $request->getQueryParams()['icon'] ?? ''), true);
        $identifier = is_array($requestedIcon) && is_string($requestedIcon[0] ?? null) ? $requestedIcon[0] : '';
        [$pageId, $layoutId] = LayoutWizardIcon::parseIdentifier($identifier) ?? [0, ''];
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (
            $pageId === 0
            || !$backendUser instanceof BackendUserAuthentication
            || GeneralUtility::makeInstance(IconRegistry::class)->isRegistered($identifier)
            // permissions of the wizard showing the icon
            || !$backendUser->checkAuthMode('tt_content', 'CType', 'gridelements_pi1')
            || BackendUtility::readPageAccess($pageId, $backendUser->getPagePermsClause(Permission::PAGE_SHOW)) === false
        ) {
            return;
        }
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($pageId);
        foreach ($layoutSetup->getLayoutWizardItems(0) as $item) {
            if ((string)$item['uid'] === $layoutId && empty($item['iconIdentifier'])) {
                LayoutWizardIcon::register($identifier, $item);
                return;
            }
        }
    }
}
