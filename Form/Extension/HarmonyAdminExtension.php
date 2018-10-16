<?php

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
     * {@inheritdoc}
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

        if ($request->attributes->has('harmonyadmin')) {
            $harmonyadmin = $request->attributes->get('harmonyadmin');
            $entity = $harmonyadmin['entity'];
            $action = $harmonyadmin['view'];
            $fields = $entity[$action]['fields'] ?? [];
            $view->vars['harmonyadmin'] = [
                'entity' => $entity,
                'view' => $action,
                'item' => $harmonyadmin['item'],
                'field' => null,
                'form_group' => $form->getConfig()->getAttribute('harmonyadmin_form_group'),
                'form_tab' => $form->getConfig()->getAttribute('harmonyadmin_form_tab'),
            ];

            /*
             * Checks if current form view is direct child on the topmost form
             * (ie. this form view`s field exists in harmonyadmin configuration)
             */
            if (null !== $view->parent && null === $view->parent->parent) {
                $view->vars['harmonyadmin']['field'] = $fields[$view->vars['name']] ?? null;
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
}
