<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\MongoDBBundle\Form\DoctrineMongoDBTypeGuesser;
use Doctrine\DBAL\Types\Type as DoctrineDBALType;
use Harmony\Bundle\AdminBundle\Form\Guesser\MissingDoctrineOdmTypeGuesser;
use Harmony\Bundle\AdminBundle\Form\Guesser\MissingDoctrineOrmTypeGuesser;
use Harmony\Bundle\AdminBundle\Form\Type\Configurator\TypeConfiguratorInterface;
use Harmony\Bundle\AdminBundle\Form\Type\FormType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormTypeGuesserChain;
use function array_keys;
use function array_map;
use function class_exists;
use function iterator_to_array;
use function sprintf;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class HarmonyAdminFormTypePass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @throws \ReflectionException
     */
    public function process(ContainerBuilder $container)
    {
        $this->configureTypeGuesserChain($container);
        $this->registerTypeConfigurators($container);
    }

    /**
     * @param ContainerBuilder $container
     */
    private function configureTypeGuesserChain(ContainerBuilder $container)
    {
        $guesserIds = array_keys($container->findTaggedServiceIds('form.type_guesser'));
        $guessers   = array_map(function ($id) {
            return new Reference($id);
        }, $guesserIds);

        if (class_exists(DoctrineDBALType::class)) {
            $guessers[] = new Reference(MissingDoctrineOrmTypeGuesser::class);
        }
        if (class_exists(DoctrineMongoDBTypeGuesser::class)) {
            $guessers[] = new Reference(MissingDoctrineOdmTypeGuesser::class);
        }

        $container->autowire(FormTypeGuesserChain::class, FormTypeGuesserChain::class)
            ->setArgument('$guessers', $guessers);
    }

    /**
     * @param ContainerBuilder $container
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function registerTypeConfigurators(ContainerBuilder $container)
    {
        $configurators = new \SplPriorityQueue();
        foreach ($container->findTaggedServiceIds('harmony_admin.form.type.configurator') as $id => $tags) {
            $configuratorClass         = new \ReflectionClass($container->getDefinition($id)->getClass());
            $typeConfiguratorInterface = TypeConfiguratorInterface::class;
            if (!$configuratorClass->implementsInterface($typeConfiguratorInterface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id,
                    $typeConfiguratorInterface));
            }

            // Register the Ivory CKEditor type configurator only if the bundle
            // is installed and no default configuration is provided.
            if ('harmony_admin.form.type.configurator.ivory_ckeditor' === $id &&
                !($container->has('ivory_ck_editor.config_manager') &&
                    null === $container->get('ivory_ck_editor.config_manager')->getDefaultConfig())) {
                $container->removeDefinition('harmony_admin.form.type.configurator.ivory_ckeditor');
                continue;
            }

            foreach ($tags as $tag) {
                $priority = $tag['priority'] ?? 0;
                $configurators->insert(new Reference($id), $priority);
            }
        }

        $container->getDefinition(FormType::class)->setArgument('$configurators', iterator_to_array($configurators));
    }
}
