<?php

namespace Harmony\Bundle\AdminBundle\DependencyInjection\Compiler;

use Harmony\Bundle\AdminBundle\Form\Type\Configurator\TypeConfiguratorInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class HarmonyAdminFormTypePass implements CompilerPassInterface
{

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->configureTypeGuesserChain($container);
        $this->registerTypeConfigurators($container);
    }

    private function configureTypeGuesserChain(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('harmonyadmin.form.type_guesser_chain');
        $guesserIds = array_keys($container->findTaggedServiceIds('form.type_guesser'));
        $guessers   = array_map(function ($id) {
            return new Reference($id);
        }, $guesserIds);
        $definition->replaceArgument(0, $guessers);
    }

    private function registerTypeConfigurators(ContainerBuilder $container)
    {
        $configurators = new \SplPriorityQueue();
        foreach ($container->findTaggedServiceIds('harmonyadmin.form.type.configurator') as $id => $tags) {
            $configuratorClass         = new \ReflectionClass($container->getDefinition($id)->getClass());
            $typeConfiguratorInterface = TypeConfiguratorInterface::class;
            if (!$configuratorClass->implementsInterface($typeConfiguratorInterface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id,
                    $typeConfiguratorInterface));
            }

            // Register the Ivory CKEditor type configurator only if the bundle
            // is installed and no default configuration is provided.
            if ('harmonyadmin.form.type.configurator.ivory_ckeditor' === $id &&
                !($container->has('ivory_ck_editor.config_manager') &&
                    null === $container->get('ivory_ck_editor.config_manager')->getDefaultConfig())) {
                $container->removeDefinition('harmonyadmin.form.type.configurator.ivory_ckeditor');
                continue;
            }

            foreach ($tags as $tag) {
                $priority = $tag['priority'] ?? 0;
                $configurators->insert(new Reference($id), $priority);
            }
        }

        $container->getDefinition('harmonyadmin.form.type')->replaceArgument(1, iterator_to_array($configurators));
    }
}
