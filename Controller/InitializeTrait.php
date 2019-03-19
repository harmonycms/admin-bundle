<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Harmony\Bundle\AdminBundle\Event\HarmonyAdminEvents;
use Harmony\Bundle\AdminBundle\Exception\UndefinedEntityException;
use Harmony\Bundle\AdminBundle\Search\Paginator as SearchPaginator;
use Harmony\Bundle\AdminBundle\Search\QueryBuilder as SearchQueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use function array_key_exists;

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

    /** @var array $model The full configuration of the current entity */
    protected $model = [];

    /** @var Request $request The instance of the current Symfony request */
    protected $request;

    /** @var ObjectManager $objectManager The Doctrine entity manager for the current entity */
    protected $objectManager;

    /** @var EventDispatcherInterface $dispatcher */
    protected $dispatcher;

    /** @var ConfigManager $configManager */
    protected $configManager;

    /** @var SearchQueryBuilder $searchQueryBuilder */
    protected $searchQueryBuilder;

    /** @var SearchPaginator $searchPaginator */
    protected $searchPaginator;

    /**
     * InitializeTrait constructor.
     *
     * @param EventDispatcherInterface $dispatcher
     * @param ConfigManager            $configManager
     * @param SearchQueryBuilder       $searchQueryBuilder
     * @param SearchPaginator          $searchPaginator
     */
    public function __construct(EventDispatcherInterface $dispatcher, ConfigManager $configManager,
                                SearchQueryBuilder $searchQueryBuilder, SearchPaginator $searchPaginator)
    {
        $this->dispatcher         = $dispatcher;
        $this->configManager      = $configManager;
        $this->searchQueryBuilder = $searchQueryBuilder;
        $this->searchPaginator    = $searchPaginator;
    }

    /**
     * Utility method which initializes the configuration of the entity on which
     * the user is performing the action.
     *
     * @param Request     $request
     * @param null|string $modelName
     */
    protected function initialize(Request $request, ?string $modelName = null)
    {
        $this->dispatch(HarmonyAdminEvents::PRE_INITIALIZE);

        $this->config = $this->configManager->getBackendConfig();

        // this condition happens when accessing the backend homepage and before
        // redirecting to the default page set as the homepage
        if (null === $modelName) {
            return;
        }

        if (!array_key_exists($modelName, $this->config['models'])) {
            throw new UndefinedEntityException(['entity_name' => $modelName]);
        }

        $this->model = $this->configManager->getEntityConfig($modelName);

        $action = $request->query->get('action', 'list');
        if (!$request->query->has('sortField')) {
            $sortField = isset($this->model[$action]['sort']['field']) ? $this->model[$action]['sort']['field'] :
                $this->model['primary_key_field_name'];
            $request->query->set('sortField', $sortField);
        }
        if (!$request->query->has('sortDirection')) {
            $sortDirection = isset($this->model[$action]['sort']['direction']) ?
                $this->model[$action]['sort']['direction'] : 'DESC';
            $request->query->set('sortDirection', $sortDirection);
        }

        $this->objectManager = $this->getDoctrine()->getManagerForClass($this->model['class']);
        $this->request       = $request;

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
            'em'      => $this->objectManager,
            'model'   => $this->model,
            'request' => $this->request,
        ], $arguments);
        $subject   = $arguments['paginator'] ?? $arguments['model'];
        $event     = new GenericEvent($subject, $arguments);
        $this->dispatcher->dispatch($eventName, $event);
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
        /** @var ObjectManager $objectManager */
        $objectManager = $this->getDoctrine()->getManagerForClass($class);
        $qb            = $objectManager->createQueryBuilder('model');
        $qb->select('count(entity.id)');
        $qb->from($class, 'model');
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