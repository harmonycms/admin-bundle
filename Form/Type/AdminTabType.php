<?php

namespace Harmony\Bundle\AdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

/**
 * The 'tab' form type is a special form type used to display a design
 * element needed to create complex form layouts. This "fake" type just displays
 * some HTML tags and it must be added to a form as "unmapped" and "non required".
 */
class AdminTabType extends AbstractType
{

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'harmony_admin_tab';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }
}
