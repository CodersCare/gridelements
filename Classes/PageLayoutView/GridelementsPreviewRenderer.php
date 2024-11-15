<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\PageLayoutView;

use Doctrine\DBAL\Exception;
use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Helper\GridElementsHelper;
use GridElementsTeam\Gridelements\View\BackendLayout\Grid\GridelementsGridColumn;
use GridElementsTeam\Gridelements\View\BackendLayout\Grid\GridelementsGridColumnItem;
use TYPO3\CMS\Backend\Preview\PreviewRendererInterface;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\Grid;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridRow;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Fluid\View\StandaloneView;

class GridelementsPreviewRenderer extends StandardContentPreviewRenderer implements PreviewRendererInterface
{
    /**
     * @var array
     */
    protected $extentensionConfiguration;

    /**
     * @var GridelementsHelper
     */
    protected GridelementsHelper $helper;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var LanguageService
     */
    protected LanguageService $languageService;

    /**
     * Stores whether a certain language has translations in it
     *
     * @var array
     */
    protected array $languageHasTranslationsCache = [];

    /**
     * @var bool
     */
    protected bool $showHidden;

    /**
     * @var string
     */
    protected string $backPath = '';

    public function __construct()
    {
        $this->extentensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('gridelements');
        $this->helper = GridelementsHelper::getInstance();
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->cleanupCollapsedStatesInUC();
    }

    /**
     * Processes the collapsed states of Gridelements columns and removes columns with 0 values
     */
    public function cleanupCollapsedStatesInUC(): void
    {
        $backendUser = $this->getBackendUser();
        if (!empty($backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'])
            && is_array($backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'])) {
            $collapsedGridelementColumns = $backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'];
            foreach ($collapsedGridelementColumns as $item => $collapsed) {
                if (empty($collapsed)) {
                    unset($collapsedGridelementColumns[$item]);
                }
            }
            $backendUser->uc['moduleData']['page']['gridelementsCollapsedColumns'] = $collapsedGridelementColumns;
            $backendUser->writeUC();
        }
    }

    /**
     * Dedicated method for rendering preview body HTML for
     * the page module only. Receives the GridColumnItem
     * that contains the record for which a preview should be
     * rendered and returned.
     *
     * @param GridColumnItem $item
     * @return string
     */
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $record = $item->getRecord();
        // Check if a Fluid-based preview template was defined for this CType
        // and render it via Fluid. Possible option:
        // mod.web_layout.tt_content.preview.media = EXT:site_mysite/Resources/Private/Templates/Preview/Media.html
        $infoArr = [];
        $this->getProcessedValue($item, 'header_position,header_layout,header_link', $infoArr);
        $tsConfig = BackendUtility::getPagesTSconfig($record['pid'])['mod.']['web_layout.']['tt_content.']['preview.'] ?? [];
        if (!empty($tsConfig[$record['CType']]) || !empty($tsConfig[$record['CType'] . '.'])) {
            $fluidPreview = $this->renderContentElementPreviewFromFluidTemplate($record);
            if ($fluidPreview !== null) {
                return $fluidPreview;
            }
        }

        return $this->renderGridContainer($item);
    }

    /**
     * @param GridColumnItem $item
     * @return string
     * @throws Exception
     */
    protected function renderGridContainer(GridColumnItem $item): string
    {
        $context = $item->getContext();
        $record = $item->getRecord();
        $grid = GeneralUtility::makeInstance(Grid::class, $context);
        $helper = GeneralUtility::makeInstance(GridElementsHelper::class);
        $gridContainerId = $record['uid'];
        $pageId = $record['pid'];
        if ($pageId < 0) {
            $originalRecord = BackendUtility::getRecord('tt_content', $record['t3ver_oid']);
        } else {
            $originalRecord = $record;
        }
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init($originalRecord['pid']);
        $gridElement = $layoutSetup->cacheCurrentParent($gridContainerId, true);
        $layoutId = $gridElement['tx_gridelements_backend_layout'];
        $layout = $layoutSetup->getLayoutSetup($layoutId);
        $layoutColumns = $layoutSetup->getLayoutColumns($layoutId);
        $activeColumns = [];
        if (!empty($layoutColumns['CSV'])) {
            $activeColumns = array_flip(GeneralUtility::intExplode(',', $layoutColumns['CSV']));
        }

        if (isset($layout['config']['rows.'])) {
            $children = $helper->getChildren('tt_content', $gridContainerId, $pageId, 'sorting', 0, '*');
            $childColumns = [];
            foreach ($children as $childRecord) {
                if (isset($childRecord['tx_gridelements_columns'])) {
                    $childColumns[$childRecord['tx_gridelements_columns']][] = $childRecord;
                }
            }
            foreach ($layout['config']['rows.'] as $row) {
                $gridRow = GeneralUtility::makeInstance(GridRow::class, $context);
                if (isset($row['columns.'])) {
                    foreach ($row['columns.'] as $column) {
                        $gridColumn = GeneralUtility::makeInstance(GridelementsGridColumn::class, $context, $column, 'tt_content', $gridContainerId);
                        $gridColumn->setRestrictions($layoutColumns);
                        if (isset($column['colPos']) && isset($activeColumns[$column['colPos']])) {
                            $gridColumn->setActive();
                        }
                        $gridRow->addColumn($gridColumn);
                        if (isset($column['colPos']) && isset($childColumns[$column['colPos']])) {
                            $gridColumn->setCollapsed(!empty($this->helper->getBackendUser()->uc['moduleData']['page']['gridelementsCollapsedColumns'][$gridContainerId . '_' . $column['colPos']]));
                            foreach ($childColumns[$column['colPos']] as $child) {
                                $gridColumnItem = GeneralUtility::makeInstance(GridelementsGridColumnItem::class, $context, $gridColumn, $child, 'tt_content', $layoutColumns);
                                $gridColumn->addItem($gridColumnItem);
                            }
                        }
                    }
                }
                $grid->addRow($gridRow);
            }
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $configurationManager = GeneralUtility::makeInstance(BackendConfigurationManager::class);
        $configuration = $configurationManager->getConfiguration('gridelements');
        $view->setTemplate($configuration['backendContainer']['view']['defaultTemplate'] ?? 'BackendContainer');
        $view->setLayoutRootPaths($configuration['backendContainer']['view']['layoutRootPaths'] ?? []);
        $view->setTemplateRootPaths($configuration['backendContainer']['view']['templateRootPaths'] ?? []);
        $view->setPartialRootPaths($configuration['backendContainer']['view']['partialRootPaths'] ?? []);

        $view->assignMultiple([
            'context' => $context,
            'typo3Version' => GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion(),
            'hideRestrictedColumns' => (bool)(BackendUtility::getPagesTSconfig($context->getPageId())['mod.']['web_layout.']['hideRestrictedCols'] ?? false),
            'newContentTitle' => $this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_layout.xlf:newContentElement'),
            'newContentTitleShort' => $this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_layout.xlf:content'),
            'allowEditContent' => $this->getBackendUser()->check('tables_modify', 'tt_content'),
            'maxTitleLength' => $this->getBackendUser()->uc['titleLen'] ?? 20,
            'gridElementsBackendLayout' => $layout,
            'gridElementsContainer' => $grid,
        ]);

        return $view->render();
    }
}
