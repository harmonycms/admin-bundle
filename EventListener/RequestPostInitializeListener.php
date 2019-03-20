<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;
use Harmony\Bundle\AdminBundle\Exception\ModelNotFoundException;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use function sprintf;

/**
 * Adds some custom attributes to the request object to store information
 * related to HarmonyAdmin.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class RequestPostInitializeListener
{

    /** @var RequestStack|null $requestStack */
    private $requestStack;

    /** @var ManagerRegistry $registry */
    private $registry;

    /**
     * @param ManagerRegistry   $registry
     * @param RequestStack|null $requestStack
     */
    public function __construct(ManagerRegistry $registry, RequestStack $requestStack = null)
    {
        $this->registry     = $registry;
        $this->requestStack = $requestStack;
    }

    /**
     * Adds to the request some attributes with useful information, such as the
     * current model and the selected item, if any.
     *
     * @param GenericEvent $event
     */
    public function initializeRequest(GenericEvent $event)
    {
        $request = null;
        if (null !== $this->requestStack) {
            $request = $this->requestStack->getCurrentRequest();
        }

        if (null === $request) {
            return;
        }

        $request->attributes->set('harmony_admin', [
            'model' => $model = $event->getArgument('model'),
            'view'  => $request->query->get('action', 'list'),
            'item'  => ($id = $request->query->get('id')) ? $this->findCurrentItem($model, $id) : null,
        ]);
    }

    /**
     * Looks for the object that corresponds to the selected 'id' of the current model.
     *
     * @param array $modelConfig
     * @param mixed $itemId
     *
     * @return object The model
     * @throws ModelNotFoundException
     * @throws \RuntimeException
     */
    private function findCurrentItem(array $modelConfig, $itemId)
    {
        if (null === $manager = $this->registry->getManagerForClass($modelConfig['class'])) {
            throw new \RuntimeException(sprintf('There is no Doctrine Entity Manager defined for the "%s" class',
                $modelConfig['class']));
        }

        if (null === $model = $manager->getRepository($modelConfig['class'])->find($itemId)) {
            throw new ModelNotFoundException([
                'model_name'     => $modelConfig['name'],
                'model_id_name'  => $modelConfig['primary_key_field_name'],
                'model_id_value' => $itemId
            ]);
        }

        return $model;
    }
}
