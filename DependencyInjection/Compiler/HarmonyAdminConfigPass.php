<?php

namespace Harmony\Bundle\AdminBundle\DependencyInjection\Compiler;

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
        $configPasses = $this->findAndSortTaggedServices('harmonyadmin.config_pass', $container);
        $definition   = $container->getDefinition('harmonyadmin.config.manager');

        foreach ($configPasses as $service) {
            $definition->addMethodCall('addConfigPass', [$service]);
        }
    }
}
