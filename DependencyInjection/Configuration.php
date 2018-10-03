<?php

namespace Harmony\Bundle\AdminBundle\DependencyInjection;

use Harmony\Bundle\CoreBundle\Component\Config\Definition\Builder\TreeBuilder;
use Harmony\Bundle\CoreBundle\DependencyInjection\HarmonyCoreExtension;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 *
 * @package Harmony\Bundle\AdminBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root(HarmonyCoreExtension::ALIAS);

        $rootNode
            ->ignoreExtraKeys(true)
            ->children()
                ->arrayNode('dashboard')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('title')
                            ->defaultValue('Dashboard')
                            ->info('The title displayed at the top of dashboard page.')
                        ->end()
                        ->arrayNode('blocks')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name', false)
                            ->defaultValue([])
                            ->info('The list of blocks to display in the dashboard page.')
                            ->prototype('variable')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}