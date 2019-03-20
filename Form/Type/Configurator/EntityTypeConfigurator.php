<?php

namespace Harmony\Bundle\AdminBundle\Form\Type\Configurator;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormConfigInterface;

/**
 * This configurator is applied to any form field of type 'association' and is
 * used to configure lots of their features (for example whether we should use
 * a JavaScript widget to display their contents).
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class EntityTypeConfigurator implements TypeConfiguratorInterface
{

    /**
     * {@inheritdoc}
     */
    public function configure($name, array $options, array $metadata, FormConfigInterface $parentConfig)
    {
        if (!isset($options['multiple']) && $metadata['associationType'] & 12) {
            $options['multiple'] = true;
        }

        // Supported associations are displayed using advanced JavaScript widgets
        $options['attr']['data-widget'] = 'select2';

        // Configure "placeholder" option for entity fields
        if (($metadata['associationType'] & 3) && !isset($options['placeholder']) && isset($options['required']) &&
            false === $options['required']) {
            $options['placeholder'] = 'label.form.empty_value';
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($type, array $options, array $metadata)
    {
        $isEntityType = \in_array($type, ['entity', EntityType::class], true);

        return $isEntityType && 'association' === $metadata['dataType'];
    }
}
