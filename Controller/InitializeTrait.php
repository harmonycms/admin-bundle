<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Harmony\Bundle\AdminBundle\Event\HarmonyAdminEvents;
use Harmony\Bundle\AdminBundle\Exception\UndefinedModelException;
use Harmony\Bundle\AdminBundle\Search\DoctrineBuilderRegistry;
use Harmony\Bundle\AdminBundle\Search\Paginator as SearchPaginator;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use function array_key_exists;
use function array_replace;
use function count;
use function is_numeric;
use function method_exists;
use function strrpos;
use function substr;

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

    /** @var array $model The full configuration of the current model */
    protected $model = [];

    /** @var Request $request The instance of the current Symfony request */
    protected $request;

    /** @var ObjectManager $objectManager The Doctrine object manager for the current model */
    protected $objectManager;

    /** @var EventDispatcherInterface $dispatcher */
    protected $dispatcher;

    /** @var ConfigManager $configManager */
    protected $configManager;

    /** @var DoctrineBuilderRegistry $builderRegistry */
    protected $builderRegistry;

    /** @var SearchPaginator $searchPaginator */
    protected $searchPaginator;

    /** @var PropertyAccessorInterface $propertyAccessor */
    protected $propertyAccessor;

    /**
     * InitializeTrait constructor.
     *
     * @param EventDispatcherInterface  $dispatcher
     * @param ConfigManager             $configManager
     * @param DoctrineBuilderRegistry   $builderRegistry
     * @param SearchPaginator           $searchPaginator
     * @param PropertyAccessorInterface $propertyAccessor
     */
    public function __construct(EventDispatcherInterface $dispatcher, ConfigManager $configManager,
                                DoctrineBuilderRegistry $builderRegistry, SearchPaginator $searchPaginator,
                                PropertyAccessorInterface $propertyAccessor)
    {
        $this->dispatcher       = $dispatcher;
        $this->configManager    = $configManager;
        $this->builderRegistry  = $builderRegistry;
        $this->searchPaginator  = $searchPaginator;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * Utility method which initializes the configuration of the model on which
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
            throw new UndefinedModelException(['model_name' => $modelName]);
        }

        $this->model = $this->configManager->getModelConfig($modelName);

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
    protected function guessModelFromClass(string $className): string
    {
        return (string)substr($className, strrpos($className, '\\') + 1);
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
        $qb->select('count(model.id)');
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