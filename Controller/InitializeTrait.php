<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\ORM\EntityManager;
use Harmony\Bundle\AdminBundle\Event\HarmonyAdminEvents;
use Harmony\Bundle\AdminBundle\Exception\UndefinedEntityException;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trait InitializeTrait
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
trait InitializeTrait
{

    use ControllerTrait;

    /** @var array $config The full configuration of the entire backend */
    protected $config;

    /** @var array $entity The full configuration of the current entity */
    protected $entity = [];

    /** @var Request $request The instance of the current Symfony request */
    protected $request;

    /** @var EntityManager $em The Doctrine entity manager for the current entity */
    protected $em;

    /**
     * Utility method which initializes the configuration of the entity on which
     * the user is performing the action.
     *
     * @param Request     $request
     * @param null|string $entityName
     */
    protected function initialize(Request $request, ?string $entityName = null)
    {
        $this->dispatch(HarmonyAdminEvents::PRE_INITIALIZE);

        $configManager = $this->get('harmony_admin.config.manager');

        $this->config = $configManager->getBackendConfig();

        // this condition happens when accessing the backend homepage and before
        // redirecting to the default page set as the homepage
        if (null === $entityName) {
            return;
        }

        if (!array_key_exists($entityName, $this->config['entities'])) {
            throw new UndefinedEntityException(['entity_name' => $entityName]);
        }

        $this->entity = $configManager->getEntityConfig($entityName);

        $action = $request->query->get('action', 'list');
        if (!$request->query->has('sortField')) {
            $sortField = isset($this->entity[$action]['sort']['field']) ? $this->entity[$action]['sort']['field'] :
                $this->entity['primary_key_field_name'];
            $request->query->set('sortField', $sortField);
        }
        if (!$request->query->has('sortDirection')) {
            $sortDirection = isset($this->entity[$action]['sort']['direction']) ?
                $this->entity[$action]['sort']['direction'] : 'DESC';
            $request->query->set('sortDirection', $sortDirection);
        }

        $this->em      = $this->getDoctrine()->getManagerForClass($this->entity['class']);
        $this->request = $request;

        $this->dispatch(HarmonyAdminEvents::POST_INITIALIZE);
    }

    /**
     * @param       $eventName
     * @param array $arguments
     */
    protected function dispatch($eventName, array $arguments = [])
    {
        $arguments = array_replace([
            'config'  => $this->config,
            'em'      => $this->em,
            'entity'  => $this->entity,
            'request' => $this->request,
        ], $arguments);
        $subject   = $arguments['paginator'] ?? $arguments['entity'];
        $event     = new GenericEvent($subject, $arguments);
        $this->get('event_dispatcher')->dispatch($eventName, $event);
    }

    /**
     * @param string $className
     *
     * @return string
     */
    protected function guessEntityFromClass(string $className): string
    {
        $entity_name = substr($className, strrpos($className, '\\') + 1);

        return (string)$entity_name;
    }

    /**
     * @param $class
     * @param $dql_filter
     *
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getBlockCount($class, $dql_filter)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManagerForClass($class);
        $qb = $em->createQueryBuilder('entity');
        $qb->select('count(entity.id)');
        $qb->from($class, 'entity');
        if ($dql_filter) {
            $qb->where($dql_filter);
        }
        $count = $qb->getQuery()->getSingleScalarResult();

        return $count;
    }

    /**
     * @param $class
     * @param $query
     *
     * @return int|string
     * @throws \ErrorException
     */
    protected function executeCustomQuery($class, $query)
    {
        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository($class);
        if (!method_exists($repo, $query)) {
            throw new \ErrorException($query . ' is not a valid function.');
        }
        $q     = $repo->{$query}();
        $count = is_numeric($q) ? $q : count($q);

        return $count;
    }
}