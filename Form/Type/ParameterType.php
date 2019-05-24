<?php

namespace Harmony\Bundle\AdminBundle\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Harmony\Bundle\CoreBundle\Model\ParameterInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ParameterType
 *
 * @package Harmony\Bundle\AdminBundle\Form\Type
 */
class ParameterType extends AbstractType
{

    /** @var ManagerRegistry $registry */
    protected $registry;

    /**
     * ParameterType constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Builds the form.
     * This method is called for each type in the hierarchy starting from the
     * top most type. Type extensions can further modify the form.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The options
     *
     * @see FormTypeExtensionInterface::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class)
            ->add('value', TextType::class)
            ->add('submit', SubmitType::class);
    }

    /**
     * Configures the options for this type.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->registry->getManager()->getClassMetadata(ParameterInterface::class)->getName()
        ]);
    }
}