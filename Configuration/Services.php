<?php

declare(strict_types=1);

use GridElementsTeam\Gridelements\ContextMenu\ItemProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Information\Typo3Version;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void {
    if ((new Typo3Version())->getMajorVersion() < 12) {
        return;
    }
    $containerBuilder->addCompilerPass(new class implements CompilerPassInterface {
        public function process(ContainerBuilder $container): void
        {
            $definition = $container->findDefinition(ItemProvider::class);
            if (!$definition->hasTag('backend.contextmenu.itemprovider')) {
                $definition->addTag('backend.contextmenu.itemprovider');
            }
        }
    });
};
