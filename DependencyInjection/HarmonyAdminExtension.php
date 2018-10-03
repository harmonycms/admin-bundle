<?php

namespace Harmony\Bundle\AdminBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class HarmonyAdminExtension
 *
 * @package Harmony\Bundle\AdminBundle\DependencyInjection
 */
class HarmonyAdminExtension extends Extension implements PrependExtensionInterface
{

    /**
     * Loads a specific configuration.
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
    }

    /**
     * Allow an extension to prepend the extension configurations.
     *
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function prepend(ContainerBuilder $container)
    {
        // get all bundles
        $bundles = $container->getParameter('kernel.bundles');

        // determine if EasyAdminBundle is registered before loading configuration
        if (isset($bundles['EasyAdminBundle'])) {
            $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
            $loader->load('easyadmin.yaml');
        }
    }
}