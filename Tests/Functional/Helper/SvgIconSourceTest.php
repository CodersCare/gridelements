<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Functional\Helper;

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\EventListener\ModifyNewContentElementWizardItemsListener;
use GridElementsTeam\Gridelements\Helper\SvgIconSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Icon sources given as path below the web root (e.g. FAL icons as '/' . getPublicUrl()) must
 * produce SVG markup with the core icon providers of TYPO3 12 and 13.
 */
class SvgIconSourceTest extends FunctionalTestCase
{
    private const WEBROOT_SVG = '/typo3temp/assets/gridelements-icon-test/icon_benefits-country.svg';
    private const WEBROOT_LARGE_SVG = '/typo3temp/assets/gridelements-icon-test/icon_benefits-country-large.svg';
    private const WEBROOT_PNG = '/typo3temp/assets/gridelements-icon-test/icon.png';
    // A public FAL storage outside fileadmin (basePath home/global/)
    private const GLOBAL_STORAGE_SVG = '/home/global/design/gridelements/icon_benefits-country.svg';
    private const FILEADMIN_SVG = '/fileadmin/gridelements-icon-test/icon_benefits-country.svg';
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';

    protected array $testExtensionsToLoad = ['gridelementsteam/gridelements'];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/typo3temp/assets/gridelements-icon-test');
        file_put_contents(Environment::getPublicPath() . self::WEBROOT_SVG, self::SVG);
        file_put_contents(Environment::getPublicPath() . self::WEBROOT_LARGE_SVG, self::SVG);
        foreach ([self::GLOBAL_STORAGE_SVG, self::FILEADMIN_SVG] as $file) {
            GeneralUtility::mkdir_deep(dirname(Environment::getPublicPath() . $file));
            file_put_contents(Environment::getPublicPath() . $file, self::SVG);
        }
        file_put_contents(Environment::getPublicPath() . self::WEBROOT_PNG, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir(Environment::getPublicPath() . '/typo3temp/assets/gridelements-icon-test', true);
        GeneralUtility::rmdir(Environment::getPublicPath() . '/home', true);
        GeneralUtility::rmdir(Environment::getPublicPath() . '/fileadmin/gridelements-icon-test', true);
        parent::tearDown();
    }

    #[Test]
    public function webrootPathIsMappedToThePublicFile(): void
    {
        self::assertSame(Environment::getPublicPath() . self::WEBROOT_SVG, SvgIconSource::resolve(self::WEBROOT_SVG));
    }

    public static function unchangedSourcesDataProvider(): array
    {
        return [
            'extension resource' => ['EXT:gridelements/Resources/Public/Icons/gridelements.svg'],
            'FAL identifier' => ['FAL:1:/gridelements-icon-test/icon_benefits-country.svg'],
            'relative public path' => ['typo3temp/assets/gridelements-icon-test/icon_benefits-country.svg'],
            'https URL' => ['https://example.org/icon.svg'],
            'protocol relative URL' => ['//example.org/icon.svg'],
            'missing webroot file' => ['/typo3temp/assets/gridelements-icon-test/missing.svg'],
            'back path' => ['/../typo3temp/assets/gridelements-icon-test/icon_benefits-country.svg'],
        ];
    }

    #[Test]
    #[DataProvider('unchangedSourcesDataProvider')]
    public function otherSourcesAreKeptUnchanged(string $source): void
    {
        self::assertSame($source, SvgIconSource::resolve($source));
    }

    #[Test]
    public function existingAbsoluteFilePathIsKeptUnchanged(): void
    {
        $source = Environment::getPublicPath() . self::WEBROOT_SVG;

        self::assertSame($source, SvgIconSource::resolve($source));
    }

    #[Test]
    public function layoutSelectIconFromWebrootPathRendersSvgMarkup(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init(0);
        $layoutSetup->setLayoutSetup(['webroot_layout' => ['title' => 'Webroot', 'icon' => [self::WEBROOT_SVG]]]);

        $selectItems = $layoutSetup->getLayoutSelectItems(0);

        self::assertSame('gridelements-select-icon-webroot_layout', $selectItems[0][2]);
        $this->assertSvgIconRenders('gridelements-select-icon-webroot_layout');
    }

    #[Test]
    public function wizardIconsFromWebrootPathsRenderSvgMarkup(): void
    {
        $gridItems = [
            'webroot_layout' => [
                'uid' => 'webroot_layout',
                'title' => 'Webroot',
                'icon' => [self::WEBROOT_SVG, self::WEBROOT_LARGE_SVG],
            ],
        ];
        $wizardItems = [];
        (new \ReflectionClass(ModifyNewContentElementWizardItemsListener::class))
            ->newInstanceWithoutConstructor()
            ->addGridItemsToWizard($gridItems, $wizardItems);

        self::assertSame('gridelements-webroot_layout', $wizardItems['gridelements_webroot_layout']['iconIdentifier']);
        $this->assertSvgIconRenders('gridelements-webroot_layout');
        $this->assertSvgIconRenders('gridelements-large-webroot_layout');
    }

    #[Test]
    public function extensionAndBitmapSourcesAreRegisteredUnchanged(): void
    {
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init(0);
        $layoutSetup->setLayoutSetup([
            'ext_layout' => ['title' => 'Extension', 'icon' => ['EXT:gridelements/Resources/Public/Icons/gridelements.svg']],
            'bitmap_layout' => ['title' => 'Bitmap', 'icon' => [self::WEBROOT_PNG]],
        ]);
        $layoutSetup->getLayoutSelectItems(0);
        $wizardItems = [];
        (new \ReflectionClass(ModifyNewContentElementWizardItemsListener::class))
            ->newInstanceWithoutConstructor()
            ->addGridItemsToWizard(['bitmap_layout' => ['uid' => 'bitmap_layout', 'title' => 'Bitmap', 'icon' => [self::WEBROOT_PNG, self::WEBROOT_PNG]]], $wizardItems);

        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $extConfiguration = $iconRegistry->getIconConfigurationByIdentifier('gridelements-select-icon-ext_layout');
        self::assertSame(SvgIconProvider::class, $extConfiguration['provider']);
        self::assertSame('EXT:gridelements/Resources/Public/Icons/gridelements.svg', $extConfiguration['options']['source']);
        $this->assertSvgIconRenders('gridelements-select-icon-ext_layout');
        foreach (['gridelements-select-icon-bitmap_layout', 'gridelements-bitmap_layout', 'gridelements-large-bitmap_layout'] as $identifier) {
            $bitmapConfiguration = $iconRegistry->getIconConfigurationByIdentifier($identifier);
            self::assertSame(BitmapIconProvider::class, $bitmapConfiguration['provider'], $identifier);
            self::assertSame(self::WEBROOT_PNG, $bitmapConfiguration['options']['source'], $identifier);
        }
    }

    public static function falStorageSourcesDataProvider(): array
    {
        return [
            'public storage outside fileadmin' => [self::GLOBAL_STORAGE_SVG],
            'fileadmin storage' => [self::FILEADMIN_SVG],
        ];
    }

    #[Test]
    #[DataProvider('falStorageSourcesDataProvider')]
    public function publicUrlOfFalStorageFileIsKeptOrMappedToThePublicFile(string $webrootPath): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/svg_icon_fal_storages.csv');
        $publicUrl = ltrim($webrootPath, '/');

        self::assertSame($publicUrl, SvgIconSource::resolve($publicUrl));
        self::assertSame(Environment::getPublicPath() . $webrootPath, SvgIconSource::resolve($webrootPath));
    }

    #[Test]
    public function falIconsOfLayoutRecordsRenderSvgMarkupInSelectorAndWizard(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/svg_icon_fal_storages.csv');
        $layoutSetup = GeneralUtility::makeInstance(LayoutSetup::class)->init(0);
        // init() turns the FAL reference into the public URL of the file
        self::assertSame([ltrim(self::GLOBAL_STORAGE_SVG, '/')], $layoutSetup->getLayoutSetup('global_icon')['icon']);
        self::assertSame([ltrim(self::FILEADMIN_SVG, '/')], $layoutSetup->getLayoutSetup('fileadmin_icon')['icon']);

        $layoutSetup->getLayoutSelectItems(0);
        $this->assertSvgIconRenders('gridelements-select-icon-global_icon');
        $this->assertSvgIconRenders('gridelements-select-icon-fileadmin_icon');

        $wizardItems = [];
        (new \ReflectionClass(ModifyNewContentElementWizardItemsListener::class))
            ->newInstanceWithoutConstructor()
            ->addGridItemsToWizard($layoutSetup->getLayoutWizardItems(0), $wizardItems);
        $this->assertSvgIconRenders($wizardItems['gridelements_global_icon']['iconIdentifier']);
        $this->assertSvgIconRenders($wizardItems['gridelements_fileadmin_icon']['iconIdentifier']);
    }

    private function assertSvgIconRenders(string $identifier): void
    {
        $iconConfiguration = GeneralUtility::makeInstance(IconRegistry::class)->getIconConfigurationByIdentifier($identifier);
        self::assertSame(SvgIconProvider::class, $iconConfiguration['provider']);
        // TYPO3 12 has no IconSize enum
        $icon = $this->get(IconFactory::class)->getIcon($identifier, enum_exists(IconSize::class) ? IconSize::SMALL : 'small');
        self::assertSame($identifier, $icon->getIdentifier());
        // The inline variant is what <typo3-backend-icon> requests from the icon AJAX route.
        self::assertStringContainsString('<svg', $icon->getMarkup('inline'), $identifier . ' has no inline SVG markup');
        self::assertMatchesRegularExpression('#<img src="[^"]*icon_benefits-country(-large)?\.svg[^"]*"|<img src="[^"]*gridelements\.svg[^"]*"#', $icon->getMarkup(), $identifier . ' has no image markup');
    }
}
