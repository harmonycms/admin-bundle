<?php

namespace Harmony\Bundle\AdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class SettingsType
 *
 * @package Harmony\Bundle\AdminBundle\Form\Type
 */
class SettingsType extends AbstractType
{

    /** @var array $settingsConfiguration */
    protected $settingsConfiguration;

    /**
     * SettingsType constructor.
     *
     * @param array $settingsConfiguration
     */
    public function __construct(array $settingsConfiguration)
    {
        $this->settingsConfiguration = $settingsConfiguration;
    }

    /**
     * Builds the form.
     * This method is called for each type in the hierarchy starting from the
     * top most type. Type extensions can further modify the form.
     *
     * @see FormTypeExtensionInterface::buildForm()
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The options
     *
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        foreach ($this->settingsConfiguration as $name => $configuration) {
            // If setting's value exists in data and setting isn't disabled
            if (array_key_exists($name, $options['data']) && !in_array($name, $options['disabled_settings'])) {
                $fieldType                   = $configuration['type'];
                $fieldOptions                = $configuration['options'];
                $fieldOptions['constraints'] = $configuration['constraints'];

                // Validator constraints
                if (!empty($fieldOptions['constraints']) && is_array($fieldOptions['constraints'])) {
                    $constraints = [];
                    foreach ($fieldOptions['constraints'] as $class => $constraintOptions) {
                        if (class_exists($class)) {
                            $constraints[] = new $class($constraintOptions);
                        } else {
                            throw new \Exception(sprintf('Constraint class "%s" not found', $class));
                        }
                    }

                    $fieldOptions['constraints'] = $constraints;
                }

                // Label I18n
                $fieldOptions['label']              = 'labels.' . $name;
                $fieldOptions['translation_domain'] = 'settings';

                // Choices I18n
                if (!empty($fieldOptions['choices'])) {
                    $fieldOptions['choices'] = array_map(function ($label) use ($fieldOptions) {
                        return $fieldOptions['label'] . '_choices.' . $label;
                    }, array_combine($fieldOptions['choices'], $fieldOptions['choices']));
                }
                $builder->add($name, $fieldType, $fieldOptions);
            }
        }
        $builder->add('submit', SubmitType::class);
    }

    /**
     * Configures the options for this type.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['disabled_settings' => []]);
    }

}