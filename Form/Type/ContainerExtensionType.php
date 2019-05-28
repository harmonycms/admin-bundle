<?php

namespace Harmony\Bundle\AdminBundle\Form\Type;

use ReflectionException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use function ksort;
use function sprintf;

/**
 * Class ContainerExtensionType
 *
 * @package Harmony\Bundle\AdminBundle\Form\Type
 */
class ContainerExtensionType extends AbstractType
{

    /** @var KernelInterface $kernel */
    protected $kernel;

    /**
     * ConfigController constructor.
     *
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Builds the form.
     * This method is called for each type in the hierarchy starting from the
     * top most type. Type extensions can further modify the form.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The options
     *
     * @throws ReflectionException
     * @see FormTypeExtensionInterface::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('extension', ChoiceType::class, ['choices' => $this->listBundles()]);
    }

    /**
     * List bundles with extension alias.
     *
     * @return array
     * @throws ReflectionException
     */
    protected function listBundles(): array
    {
        //        $container = $this->compileContainer();

        $extensions = [];
        foreach ($this->kernel->getBundles() as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $extensions[sprintf('%s (%s)', $bundle->getName(), $extension->getAlias())] = $extension->getAlias();
            }
        }
        ksort($extensions);

        return $extensions;
    }
}