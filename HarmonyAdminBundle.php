<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle;

use Harmony\Bundle\AdminBundle\DependencyInjection\Compiler\HarmonyAdminConfigPass;
use Harmony\Bundle\AdminBundle\DependencyInjection\Compiler\HarmonyAdminFormTypePass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class HarmonyAdminBundle
 *
 * @package Harmony\Bundle\AdminBundle
 */
class HarmonyAdminBundle extends Bundle
{

    /** Constant */
    const VERSION = '1.0.0';

    /**
     * Builds the bundle.
     * It is only ever called once when the cache is empty.
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     *
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new HarmonyAdminFormTypePass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new HarmonyAdminConfigPass());
    }
}