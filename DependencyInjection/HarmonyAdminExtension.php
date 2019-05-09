<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\DependencyInjection;

use Harmony\Bundle\AdminBundle\Configuration\DesignConfigPass;
use Harmony\Bundle\AdminBundle\EventListener\ExceptionListener;
use Rollerworks\Bundle\RouteAutowiringBundle\RouteImporter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use function array_key_exists;
use function dirname;
use function end;
use function explode;
use function in_array;
use function is_array;
use function is_numeric;
use function preg_match;

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
        // process the configuration
        $configs = $container->getParameter('harmony_admin.config');
        $configs = $this->processConfigFiles($configs);
        // use the Configuration class to generate a config array
        $config = $this->processConfiguration(new Configuration(), $configs);
        // set parameters
        $container->setParameter('harmony_admin.config', $config);
        $container->setParameter('harmony_admin.cache.dir',
            $container->getParameter('kernel.cache_dir') . '/harmony_admin');

        $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $loader->load('services.yaml');
        $loader->load('form.yaml');

        if ($container->getParameter('kernel.debug')) {
            // in 'dev', use the built-in Symfony exception listener
            $container->removeDefinition(ExceptionListener::class);
            // avoid parsing the entire config in 'dev' (even for requests unrelated to the backend)
            $container->removeDefinition('harmony_admin.cache.config_warmer');
        }
        if ($container->hasParameter('locale')) {
            $container->getDefinition(DesignConfigPass::class)
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

        if (isset($bundles['WebpackEncoreBundle'])) {
            $loader->load('webpack_encore.yaml');
        }

        // allow twig override of HarmonyUserBundle
        $container->loadFromExtension('twig', [
            'paths' => [dirname(__DIR__) . '/Resources/views/UserBundle' => 'HarmonyUser']
        ]);
    }

    /**
     * This method allows to define the model configuration is several files.
     * Without this, Symfony doesn't merge correctly the 'models' config key
     * defined in different files.
     *
     * @param array $configs
     *
     * @return array
     */
    private function processConfigFiles(array $configs)
    {
        $existingModelNames = [];
        foreach ($configs as $i => $config) {
            if (array_key_exists('models', $config)) {
                $processedConfig = [];
                foreach ($config['models'] as $key => $value) {
                    $modelConfig                 = $this->normalizeModelConfig($key, $value);
                    $modelName                   = $this->getUniqueModelName($key, $modelConfig, $existingModelNames);
                    $modelConfig['name']         = $modelName;
                    $processedConfig[$modelName] = $modelConfig;
                    $existingModelNames[]        = $modelName;
                }
                $config['models'] = $processedConfig;
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
     * # Config format #1: no custom model name
     * easy_admin:
     *     models:
     *         - AppBundle\Entity\User
     * # Config format #2: simple config with custom model name
     * easy_admin:
     *     models:
     *         User: AppBundle\Entity\User
     * And this is the full expanded configuration syntax generated by this method:
     * # Config format #3: expanded model configuration with 'class' parameter
     * easy_admin:
     *     models:
     *         User:
     *             class: AppBundle\Entity\User
     *
     * @param mixed $modelName
     * @param mixed $modelConfig
     *
     * @return array
     * @throws \RuntimeException
     */
    private function normalizeModelConfig($modelName, $modelConfig)
    {
        // normalize config formats #1 and #2 to use the 'class' option as config format #3
        if (!is_array($modelConfig)) {
            $modelConfig = ['class' => $modelConfig];
        }
        // if config format #3 is used, ensure that it defines the 'class' option
        if (!isset($modelConfig['class'])) {
            throw new \RuntimeException(sprintf('The "%s" model must define its associated Doctrine model class using the "class" option.',
                $modelName));
        }

        return $modelConfig;
    }

    /**
     * The name of the model is included in the URLs of the backend to define
     * the model used to perform the operations. Obviously, the model name
     * must be unique to identify models unequivocally.
     * This method ensures that the given model name is unique among all the
     * previously existing models passed as the second argument. This is
     * achieved by iteratively appending a suffix until the model name is
     * guaranteed to be unique.
     *
     * @param string $modelName
     * @param array  $modelConfig
     * @param array  $existingModelNames
     *
     * @return string The model name transformed to be unique
     */
    private function getUniqueModelName($modelName, array $modelConfig, array $existingModelNames)
    {
        // the shortcut config syntax doesn't require to give models a name
        if (is_numeric($modelName)) {
            $modelClassParts = explode('\\', $modelConfig['class']);
            $modelName       = end($modelClassParts);
        }
        $i          = 2;
        $uniqueName = $modelName;
        while (in_array($uniqueName, $existingModelNames)) {
            $uniqueName = $modelName . ($i ++);
        }
        $modelName = $uniqueName;
        // make sure that the model name is valid as a PHP method name
        // (this is required to allow extending the backend with a custom controller)
        if (!$this->isValidMethodName($modelName)) {
            throw new \InvalidArgumentException(sprintf('The name of the "%s" model contains invalid characters (allowed: letters, numbers, underscores; the first character cannot be a number).',
                $modelName));
        }

        return $modelName;
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