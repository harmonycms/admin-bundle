<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\DependencyInjection\Compiler;

use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class HarmonyAdminConfigPass implements CompilerPassInterface
{

    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $configPasses = $this->findAndSortTaggedServices('harmony_admin.config_pass', $container);
        $definition   = $container->getDefinition(ConfigManager::class);

        foreach ($configPasses as $service) {
            $definition->addMethodCall('addConfigPass', [$service]);
        }
    }
}
