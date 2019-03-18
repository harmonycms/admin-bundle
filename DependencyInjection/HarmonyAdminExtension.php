<?php

namespace Harmony\Bundle\AdminBundle\DependencyInjection;

use Harmony\Bundle\CoreBundle\DependencyInjection\HarmonyCoreExtension;
use Rollerworks\Bundle\RouteAutowiringBundle\RouteImporter;
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
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // process bundle's configuration parameters
        $container->setParameter('harmony_admin.cache.dir',
            $container->getParameter('kernel.cache_dir') . '/harmony_admin');

        $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->load('services.yaml');
        $loader->load('form.yaml');

        if ($container->getParameter('kernel.debug')) {
            // in 'dev', use the built-in Symfony exception listener
            $container->removeDefinition('harmony_admin.listener.exception');
            // avoid parsing the entire config in 'dev' (even for requests unrelated to the backend)
            $container->removeDefinition('harmony_admin.cache.config_warmer');
        }
        if ($container->hasParameter('locale')) {
            $container->getDefinition('harmony_admin.configuration.design_config_pass')
                ->replaceArgument('$locale', $container->getParameter('locale'));
        }

        $routeImporter = new RouteImporter($container);
        $routeImporter->addObjectResource($this);
        $routeImporter->import('@HarmonyAdminBundle/Resources/config/routing.yaml', 'admin');
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

        $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));

        // determine if HarmonyAdminBundle is registered before loading configuration
        if (isset($bundles['HarmonyCoreBundle'])) {
            $loader->load('admin.yaml');
        }

        if (isset($bundles['WebpackEncoreBundle'])) {
            $loader->load('webpack_encore.yaml');
        }

        // process the configuration
        $configs = $container->getExtensionConfig(HarmonyCoreExtension::ALIAS);
        $configs = $this->processConfigFiles($configs);
        // use the Configuration class to generate a config array
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('harmony_admin.config', $config['admin']);
        foreach ($config as $key => $value) {
            $container->setParameter(HarmonyCoreExtension::ALIAS . '.' . $key, $value);
        }

        // allow twig override of HarmonyUserBundle
        $container->loadFromExtension('twig', [
            'paths' => [dirname(__DIR__) . '/Resources/views/UserBundle' => 'HarmonyUser']
        ]);
    }

    /**
     * This method allows to define the entity configuration is several files.
     * Without this, Symfony doesn't merge correctly the 'entities' config key
     * defined in different files.
     *
     * @param array $configs
     *
     * @return array
     */
    private function processConfigFiles(array $configs)
    {
        $existingEntityNames = [];
        foreach ($configs as $i => $config) {
            if (array_key_exists('admin', $config) && array_key_exists('entities', $config['admin'])) {
                $processedConfig = [];
                foreach ($config['admin']['entities'] as $key => $value) {
                    $entityConfig                 = $this->normalizeEntityConfig($key, $value);
                    $entityName                   = $this->getUniqueEntityName($key, $entityConfig,
                        $existingEntityNames);
                    $entityConfig['name']         = $entityName;
                    $processedConfig[$entityName] = $entityConfig;
                    $existingEntityNames[]        = $entityName;
                }
                $config['admin']['entities'] = $processedConfig;
            }
            $configs[$i] = $config;
        }

        return $configs;
    }

    /**
     * Transforms the two simple configuration formats into the full expanded
     * configuration. This allows to reuse the same method to process any of the
     * different configuration formats.
     * These are the two simple formats allowed:
     * # Config format #1: no custom entity name
     * easy_admin:
     *     entities:
     *         - AppBundle\Entity\User
     * # Config format #2: simple config with custom entity name
     * easy_admin:
     *     entities:
     *         User: AppBundle\Entity\User
     * And this is the full expanded configuration syntax generated by this method:
     * # Config format #3: expanded entity configuration with 'class' parameter
     * easy_admin:
     *     entities:
     *         User:
     *             class: AppBundle\Entity\User
     *
     * @param mixed $entityName
     * @param mixed $entityConfig
     *
     * @return array
     * @throws \RuntimeException
     */
    private function normalizeEntityConfig($entityName, $entityConfig)
    {
        // normalize config formats #1 and #2 to use the 'class' option as config format #3
        if (!\is_array($entityConfig)) {
            $entityConfig = ['class' => $entityConfig];
        }
        // if config format #3 is used, ensure that it defines the 'class' option
        if (!isset($entityConfig['class'])) {
            throw new \RuntimeException(sprintf('The "%s" entity must define its associated Doctrine entity class using the "class" option.',
                $entityName));
        }

        return $entityConfig;
    }

    /**
     * The name of the entity is included in the URLs of the backend to define
     * the entity used to perform the operations. Obviously, the entity name
     * must be unique to identify entities unequivocally.
     * This method ensures that the given entity name is unique among all the
     * previously existing entities passed as the second argument. This is
     * achieved by iteratively appending a suffix until the entity name is
     * guaranteed to be unique.
     *
     * @param string $entityName
     * @param array  $entityConfig
     * @param array  $existingEntityNames
     *
     * @return string The entity name transformed to be unique
     */
    private function getUniqueEntityName($entityName, array $entityConfig, array $existingEntityNames)
    {
        // the shortcut config syntax doesn't require to give entities a name
        if (is_numeric($entityName)) {
            $entityClassParts = explode('\\', $entityConfig['class']);
            $entityName       = end($entityClassParts);
        }
        $i          = 2;
        $uniqueName = $entityName;
        while (\in_array($uniqueName, $existingEntityNames)) {
            $uniqueName = $entityName . ($i ++);
        }
        $entityName = $uniqueName;
        // make sure that the entity name is valid as a PHP method name
        // (this is required to allow extending the backend with a custom controller)
        if (!$this->isValidMethodName($entityName)) {
            throw new \InvalidArgumentException(sprintf('The name of the "%s" entity contains invalid characters (allowed: letters, numbers, underscores; the first character cannot be a number).',
                $entityName));
        }

        return $entityName;
    }

    /**
     * Checks whether the given string is valid as a PHP method name.
     *
     * @param string $name
     *
     * @return bool
     */
    private function isValidMethodName($name)
    {
        return 0 !== preg_match('/^-?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name);
    }
}