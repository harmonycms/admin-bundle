<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Form\Extension;

use Harmony\Bundle\AdminBundle\Form\Util\FormTypeHelper;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Extension that injects HarmonyAdmin related information in the view used to
 * render the form.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 * @method iterable getExtendedTypes()
 */
class HarmonyAdminExtension extends AbstractTypeExtension
{

    /** @var RequestStack|null */
    private $requestStack;

    /**
     * @param RequestStack|null $requestStack
     */
    public function __construct(RequestStack $requestStack = null)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Finishes the view.
     * This method is called after the extended type has finished the view to
     * further modify it.
     *
     * @see FormTypeInterface::finishView()
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $request = null;
        if (null !== $this->requestStack) {
            $request = $this->requestStack->getCurrentRequest();
        }

        if (null === $request) {
            return;
        }

        if ($request->attributes->has('harmony_admin')) {
            $harmonyAdmin                = $request->attributes->get('harmony_admin');
            $entity                      = $harmonyAdmin['model'];
            $action                      = $harmonyAdmin['view'];
            $fields                      = $entity[$action]['fields'] ?? [];
            $view->vars['harmony_admin'] = [
                'model'      => $entity,
                'view'       => $action,
                'item'       => $harmonyAdmin['item'],
                'field'      => null,
                'form_group' => $form->getConfig()->getAttribute('harmony_admin_form_group'),
                'form_tab'   => $form->getConfig()->getAttribute('harmony_admin_form_tab'),
            ];

            /*
             * Checks if current form view is direct child on the topmost form
             * (ie. this form view`s field exists in harmony_admin configuration)
             */
            if (null !== $view->parent && null === $view->parent->parent) {
                $view->vars['harmony_admin']['field'] = $fields[$view->vars['name']] ?? null;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return FormTypeHelper::getTypeClass('form');
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method iterable getExtendedTypes()
    }
}
