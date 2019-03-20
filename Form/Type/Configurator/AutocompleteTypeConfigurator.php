<?php

namespace Harmony\Bundle\AdminBundle\Form\Type\Configurator;

use Harmony\Bundle\AdminBundle\Form\Type\AutocompleteType;
use Symfony\Component\Form\FormConfigInterface;
use function in_array;

/**
 * This configurator is applied to any form field of type 'harmony_admin_autocomplete'
 * and is used to configure the class of the autocompleted model.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class AutocompleteTypeConfigurator implements TypeConfiguratorInterface
{

    /**
     * {@inheritdoc}
     */
    public function configure($name, array $options, array $metadata, FormConfigInterface $parentConfig)
    {
        // by default, guess the mandatory 'class' option from the Doctrine metadata
        if (!isset($options['class']) && isset($metadata['targetEntity'])) {
            $options['class'] = $metadata['targetEntity'];
        }

        // by default, allow to autocomplete multiple values for OneToMany and ManyToMany associations
        if (!isset($options['multiple']) && isset($metadata['associationType']) && $metadata['associationType'] & 12) {
            $options['multiple'] = true;
        }

        if (null !== $metadata['label'] && !isset($options['label'])) {
            $options['label'] = $metadata['label'];
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($type, array $options, array $metadata)
    {
        $supportedTypes = [
            'harmony_admin_autocomplete',
            AutocompleteType::class,
        ];

        return in_array($type, $supportedTypes, true);
    }
}
