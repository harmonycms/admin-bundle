<?php

namespace Harmony\Bundle\AdminBundle\Form\EventListener;

use Harmony\Bundle\AdminBundle\Form\Util\FormTypeHelper;
use function is_array;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Traversable;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class HarmonyAdminAutocompleteSubscriber implements EventSubscriberInterface
{

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_SUBMIT   => 'preSubmit',
        ];
    }

    /**
     * @param FormEvent $event
     */
    public function preSetData(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData() ?: [];

        $options             = $form->getConfig()->getOptions();
        $options['compound'] = false;
        $options['choices']  = is_array($data) || $data instanceof Traversable ? $data : [$data];

        $form->add('autocomplete', FormTypeHelper::getTypeClass('model'), $options);
    }

    /**
     * @param FormEvent $event
     */
    public function preSubmit(FormEvent $event)
    {
        $data    = $event->getData();
        $form    = $event->getForm();
        $options = $form->get('autocomplete')->getConfig()->getOptions();

        if (!isset($data['autocomplete']) || '' === $data['autocomplete']) {
            $options['choices'] = [];
        } else {
            $options['choices'] = $options['em']->getRepository($options['class'])->findBy([
                $options['id_reader']->getIdField() => $data['autocomplete'],
            ]);
        }

        // reset some critical lazy options
        unset($options['em'], $options['loader'], $options['empty_data'], $options['choice_list'], $options['choices_as_values']);

        $form->add('autocomplete', FormTypeHelper::getTypeClass('model'), $options);
    }
}
