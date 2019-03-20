<?php

namespace Harmony\Bundle\AdminBundle\Form\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use function count;
use function key;

/**
 * This form event subscriber helps to provide the tab functionality.
 *
 * @author naitsirch <naitsirch@e.mail.de>
 */
class HarmonyAdminTabSubscriber implements EventSubscriberInterface
{

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::POST_SUBMIT => ['handleViolations', - 1],
        ];
    }

    /**
     * Deal with form constraint violations. This method has to be executed with
     * a negative priority to make sure that the validation process is done.
     *
     * @param FormEvent $event
     */
    public function handleViolations(FormEvent $event)
    {
        $formTabs = $event->getForm()->getConfig()->getAttribute('harmony_admin_form_tabs');

        $firstTabWithErrors = null;
        foreach ($event->getForm() as $child) {
            $errors = $child->getErrors(true);

            if (count($errors) > 0) {
                $formTab                      = $child->getConfig()->getAttribute('harmony_admin_form_tab');
                $formTabs[$formTab]['errors'] += count($errors);

                if (null === $firstTabWithErrors) {
                    $firstTabWithErrors = $formTab;
                }
            }
        }

        // ensure that the first tab with errors is displayed
        $firstTab = key($formTabs);
        if ($firstTab !== $firstTabWithErrors) {
            $formTabs[$firstTab]['active']           = false;
            $formTabs[$firstTabWithErrors]['active'] = true;
        }
    }
}
