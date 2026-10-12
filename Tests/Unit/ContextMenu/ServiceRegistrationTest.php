<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Tests\Unit\ContextMenu;

use GridElementsTeam\Gridelements\ContextMenu\ItemProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\ProviderInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ServiceRegistrationTest extends UnitTestCase
{
    private const TAG = 'backend.contextmenu.itemprovider';

    private function compileWithServicesConfiguration(bool $autoconfigured): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->registerForAutoconfiguration(ProviderInterface::class)->addTag(self::TAG);
        $containerBuilder->register(ItemProvider::class, ItemProvider::class)
            ->setPublic(true)
            ->setAutoconfigured($autoconfigured);
        (new PhpFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../../../Configuration')))->load('Services.php');
        $containerBuilder->compile();

        return $containerBuilder;
    }

    #[Test]
    public function itemProviderIsTaggedForTheRegistryOnlyFromTypo3Twelve(): void
    {
        $taggedServices = $this->compileWithServicesConfiguration(false)->findTaggedServiceIds(self::TAG);

        if ((new Typo3Version())->getMajorVersion() >= 12) {
            self::assertSame([ItemProvider::class => [[]]], $taggedServices);
        } else {
            self::assertSame([], $taggedServices);
        }
    }

    #[Test]
    public function autoconfiguredItemProviderIsNotTaggedTwice(): void
    {
        $taggedServices = $this->compileWithServicesConfiguration(true)->findTaggedServiceIds(self::TAG);

        self::assertCount(1, $taggedServices[ItemProvider::class]);
    }
}
