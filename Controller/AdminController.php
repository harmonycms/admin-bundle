<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Harmony\Bundle\AdminBundle\Event\HarmonyAdminEvents;
use Harmony\Bundle\AdminBundle\Exception\EntityRemoveException;
use Harmony\Bundle\AdminBundle\Exception\ForbiddenActionException;
use Harmony\Bundle\AdminBundle\Exception\UndefinedEntityException;
use Harmony\Bundle\AdminBundle\Form\Util\FormTypeHelper;
use Harmony\Bundle\CoreBundle\DependencyInjection\HarmonyCoreExtension;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AdminController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class AdminController extends Controller
{

    /** @var array The full configuration of the entire backend */
    protected $config;

    /** @var array The full configuration of the current entity */
    protected $entity = [];

    /** @var Request The instance of the current Symfony request */
    protected $request;

    /** @var EntityManager The Doctrine entity manager for the current entity */
    protected $em;

    /**
     * @Route("/", name="admin")
     * @param Request $request
     *
     * @return Response
     * @throws ForbiddenActionException
     * @throws \ErrorException
     */
    public function index(Request $request): Response
    {
        $this->initialize($request);

        return $this->redirectToBackendHomepage();
    }

    /**
     * @Route("/entity/{entity}", name="admin_entity")
     * @param Request $request
     * @param string  $entity
     *
     * @return mixed
     */
    public function entity(Request $request, string $entity)
    {
        $this->initialize($request, $entity);
        $action = $request->query->get('action', 'list');
        if (!$this->isActionAllowed($action)) {
            throw new ForbiddenActionException(['action' => $action, 'entity_name' => $this->entity['name']]);
        }

        return $this->executeDynamicMethod($action . '<EntityName>');
    }

    /**
     * @return Response
     * @throws \ErrorException
     */
    protected function redirectToBackendHomepage(): Response
    {
        $dashboard = $this->container->getParameter(HarmonyCoreExtension::ALIAS . '.admin')['dashboard'];
        if (!empty($dashboard['blocks'])) {
            foreach ($dashboard['blocks'] as $key => $block) {
                if (!empty($block['items'])) {
                    foreach ($block['items'] as $k => $item) {
                        if (!empty($item['query'])) {
                            $count = $this->executeCustomQuery($item['class'], $item['query']);
                        } else {
                            $count = $this->getBlockCount($item['class'],
                                !empty($item['dql_filter']) ? $item['dql_filter'] : false);
                        }
                        $dashboard['blocks'][$key]['items'][$k]['count'] = $count;

                        if (!empty($item['entity'])) {
                            $entity = $item['entity'];
                        } else {
                            $entity = $this->guessEntityFromClass($item['class']);
                        }
                        $dashboard['blocks'][$key]['items'][$k]['entity'] = $entity;
                    }
                }
            }
        }

        return $this->render('@HarmonyAdmin\default\index.html.twig', [
            'dashboard' => $dashboard
        ]);
    }

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

        $this->config = $this->get('harmonyadmin.config.manager')->getBackendConfig();

        // this condition happens when accessing the backend homepage and before
        // redirecting to the default page set as the homepage
        if (null === $entityName) {
            return;
        }

        if (!array_key_exists($entityName, $this->config['entities'])) {
            throw new UndefinedEntityException(['entity_name' => $entityName]);
        }

        $this->entity = $this->get('harmonyadmin.config.manager')->getEntityConfig($entityName);

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
     * The method that returns the values displayed by an autocomplete field
     * based on the user's input.
     *
     * @return JsonResponse
     */
    protected function autocomplete(): JsonResponse
    {
        $results = $this->get('harmonyadmin.autocomplete')
            ->find($this->request->query->get('entity'), $this->request->query->get('query'),
                $this->request->query->get('page', 1));

        return new JsonResponse($results);
    }

    /**
     * The method that is executed when the user performs a 'list' action on an entity.
     *
     * @return Response
     */
    protected function list(): Response
    {
        $this->dispatch(HarmonyAdminEvents::PRE_LIST);
        $fields    = $this->entity['list']['fields'];
        $paginator = $this->findAll($this->entity['class'], $this->request->query->get('page', 1),
            $this->entity['list']['max_results'], $this->request->query->get('sortField'),
            $this->request->query->get('sortDirection'), $this->entity['list']['dql_filter']);
        $this->dispatch(HarmonyAdminEvents::POST_LIST, ['paginator' => $paginator]);
        $parameters = [
            'paginator'            => $paginator,
            'fields'               => $fields,
            'delete_form_template' => $this->createDeleteForm($this->entity['name'], '__id__')->createView(),
        ];

        return $this->executeDynamicMethod('render<EntityName>Template',
            ['list', $this->entity['templates']['list'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'edit' action on an entity.
     *
     * @return Response|RedirectResponse
     * @throws \RuntimeException
     * @throws \ErrorException
     */
    protected function edit()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_EDIT);
        $id           = $this->request->query->get('id');
        $harmonyadmin = $this->request->attributes->get('harmonyadmin');
        $entity       = $harmonyadmin['item'];
        if ($this->request->isXmlHttpRequest() && $property = $this->request->query->get('property')) {
            $newValue       = 'true' === mb_strtolower($this->request->query->get('newValue'));
            $fieldsMetadata = $this->entity['list']['fields'];
            if (!isset($fieldsMetadata[$property]) || 'toggle' !== $fieldsMetadata[$property]['dataType']) {
                throw new \RuntimeException(sprintf('The type of the "%s" property is not "toggle".', $property));
            }
            $this->updateEntityProperty($entity, $property, $newValue);

            // cast to integer instead of string to avoid sending empty responses for 'false'
            return new Response((int)$newValue);
        }
        $fields     = $this->entity['edit']['fields'];
        $editForm   = $this->executeDynamicMethod('create<EntityName>EditForm', [$entity, $fields]);
        $deleteForm = $this->createDeleteForm($this->entity['name'], $id);
        $editForm->handleRequest($this->request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->dispatch(HarmonyAdminEvents::PRE_UPDATE, ['entity' => $entity]);
            $this->executeDynamicMethod('update<EntityName>Entity', [$entity, $editForm]);
            $this->dispatch(HarmonyAdminEvents::POST_UPDATE, ['entity' => $entity]);

            return $this->redirectToReferrer();
        }
        $this->dispatch(HarmonyAdminEvents::POST_EDIT);
        $parameters = [
            'form'          => $editForm->createView(),
            'entity_fields' => $fields,
            'entity'        => $entity,
            'delete_form'   => $deleteForm->createView(),
        ];

        return $this->executeDynamicMethod('render<EntityName>Template',
            ['edit', $this->entity['templates']['edit'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'show' action on an entity.
     *
     * @return Response
     */
    protected function show()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_SHOW);
        $id           = $this->request->query->get('id');
        $harmonyadmin = $this->request->attributes->get('harmonyadmin');
        $entity       = $harmonyadmin['item'];
        $fields       = $this->entity['show']['fields'];
        $deleteForm   = $this->createDeleteForm($this->entity['name'], $id);
        $this->dispatch(HarmonyAdminEvents::POST_SHOW, [
            'deleteForm' => $deleteForm,
            'fields'     => $fields,
            'entity'     => $entity,
        ]);
        $parameters = [
            'entity'      => $entity,
            'fields'      => $fields,
            'delete_form' => $deleteForm->createView(),
        ];

        return $this->executeDynamicMethod('render<EntityName>Template',
            ['show', $this->entity['templates']['show'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'new' action on an entity.
     *
     * @return Response|RedirectResponse
     * @throws \ErrorException
     */
    protected function new()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_NEW);
        $entity               = $this->executeDynamicMethod('createNew<EntityName>Entity');
        $harmonyadmin         = $this->request->attributes->get('harmonyadmin');
        $harmonyadmin['item'] = $entity;
        $this->request->attributes->set('harmonyadmin', $harmonyadmin);
        $fields  = $this->entity['new']['fields'];
        $newForm = $this->executeDynamicMethod('create<EntityName>NewForm', [$entity, $fields]);
        $newForm->handleRequest($this->request);
        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->dispatch(HarmonyAdminEvents::PRE_PERSIST, ['entity' => $entity]);
            $this->executeDynamicMethod('persist<EntityName>Entity', [$entity, $newForm]);
            $this->dispatch(HarmonyAdminEvents::POST_PERSIST, ['entity' => $entity]);

            return $this->redirectToReferrer();
        }
        $this->dispatch(HarmonyAdminEvents::POST_NEW, [
            'entity_fields' => $fields,
            'form'          => $newForm,
            'entity'        => $entity,
        ]);
        $parameters = [
            'form'          => $newForm->createView(),
            'entity_fields' => $fields,
            'entity'        => $entity,
        ];

        return $this->executeDynamicMethod('render<EntityName>Template',
            ['new', $this->entity['templates']['new'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'delete' action to
     * remove any entity.
     *
     * @return Response
     * @throws EntityRemoveException
     * @throws \ErrorException
     */
    protected function delete()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_DELETE);
        if ('DELETE' !== $this->request->getMethod()) {
            return $this->redirect($this->generateUrl('admin',
                ['action' => 'list', 'entity' => $this->entity['name']]));
        }
        $id   = $this->request->query->get('id');
        $form = $this->createDeleteForm($this->entity['name'], $id);
        $form->handleRequest($this->request);
        if ($form->isSubmitted() && $form->isValid()) {
            $harmonyadmin = $this->request->attributes->get('harmonyadmin');
            $entity       = $harmonyadmin['item'];
            $this->dispatch(HarmonyAdminEvents::PRE_REMOVE, ['entity' => $entity]);
            try {
                $this->executeDynamicMethod('remove<EntityName>Entity', [$entity, $form]);
            }
            catch (ForeignKeyConstraintViolationException $e) {
                throw new EntityRemoveException([
                    'entity_name' => $this->entity['name'],
                    'message'     => $e->getMessage()
                ]);
            }
            $this->dispatch(HarmonyAdminEvents::POST_REMOVE, ['entity' => $entity]);
        }
        $this->dispatch(HarmonyAdminEvents::POST_DELETE);

        return $this->redirectToReferrer();
    }

    /**
     * The method that is executed when the user performs a query on an entity.
     *
     * @return Response
     */
    protected function search()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_SEARCH);
        $query = trim($this->request->query->get('query'));
        // if the search query is empty, redirect to the 'list' action
        if ('' === $query) {
            $queryParameters = array_filter(array_replace($this->request->query->all(),
                ['action' => 'list', 'query' => null]));

            return $this->redirect($this->get('router')->generate('admin', $queryParameters));
        }
        $searchableFields     = $this->entity['search']['fields'];
        $defaultSortField     = isset($this->entity['search']['sort']['field']) ?
            $this->entity['search']['sort']['field'] : null;
        $defaultSortDirection = isset($this->entity['search']['sort']['direction']) ?
            $this->entity['search']['sort']['direction'] : null;
        $paginator            = $this->findBy($this->entity['class'], $query, $searchableFields,
            $this->request->query->get('page', 1), $this->entity['list']['max_results'],
            $this->request->query->get('sortField', $defaultSortField),
            $this->request->query->get('sortDirection', $defaultSortDirection), $this->entity['search']['dql_filter']);
        $fields               = $this->entity['list']['fields'];
        $this->dispatch(HarmonyAdminEvents::POST_SEARCH, [
            'fields'    => $fields,
            'paginator' => $paginator,
        ]);
        $parameters = [
            'paginator'            => $paginator,
            'fields'               => $fields,
            'delete_form_template' => $this->createDeleteForm($this->entity['name'], '__id__')->createView(),
        ];

        return $this->executeDynamicMethod('render<EntityName>Template',
            ['search', $this->entity['templates']['list'], $parameters]);
    }

    /**
     * It updates the value of some property of some entity to the new given value.
     *
     * @param mixed  $entity   The instance of the entity to modify
     * @param string $property The name of the property to change
     * @param bool   $value    The new value of the property
     *
     * @throws \RuntimeException
     */
    protected function updateEntityProperty($entity, $property, $value)
    {
        $entityConfig = $this->entity;
        if (!$this->get('harmony_admin.property_accessor')->isWritable($entity, $property)) {
            throw new \RuntimeException(sprintf('The "%s" property of the "%s" entity is not writable.', $property,
                $entityConfig['name']));
        }
        $this->get('harmony_admin.property_accessor')->setValue($entity, $property, $value);
        $this->dispatch(HarmonyAdminEvents::PRE_UPDATE, ['entity' => $entity, 'newValue' => $value]);
        $this->executeDynamicMethod('update<EntityName>Entity', [$entity]);
        $this->dispatch(HarmonyAdminEvents::POST_UPDATE, ['entity' => $entity, 'newValue' => $value]);
        $this->dispatch(HarmonyAdminEvents::POST_EDIT);
    }

    /**
     * Creates a new object of the current managed entity.
     * This method is mostly here for override convenience, because it allows
     * the user to use his own method to customize the entity instantiation.
     *
     * @return object
     */
    protected function createNewEntity()
    {
        $entityFullyQualifiedClassName = $this->entity['class'];

        return new $entityFullyQualifiedClassName();
    }

    /**
     * Allows applications to modify the entity associated with the item being
     * created while persisting it.
     *
     * @param object $entity
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function persistEntity($entity)
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * Allows applications to modify the entity associated with the item being
     * edited before updating it.
     *
     * @param object $entity
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function updateEntity($entity)
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * Allows applications to modify the entity associated with the item being
     * deleted before removing it.
     *
     * @param object $entity
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function removeEntity($entity)
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    /**
     * Performs a database query to get all the records related to the given
     * entity. It supports pagination and field sorting.
     *
     * @param string      $entityClass
     * @param int         $page
     * @param int         $maxPerPage
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @param string|null $dqlFilter
     *
     * @return Pagerfanta The paginated query results
     */
    protected function findAll($entityClass, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null,
                               $dqlFilter = null)
    {
        if (!\in_array(strtoupper($sortDirection), ['ASC', 'DESC'])) {
            $sortDirection = 'DESC';
        }
        $queryBuilder = $this->executeDynamicMethod('create<EntityName>ListQueryBuilder',
            [$entityClass, $sortDirection, $sortField, $dqlFilter]);
        $this->dispatch(HarmonyAdminEvents::POST_LIST_QUERY_BUILDER, [
            'query_builder'  => $queryBuilder,
            'sort_field'     => $sortField,
            'sort_direction' => $sortDirection,
        ]);

        return $this->get('harmonyadmin.paginator')->createOrmPaginator($queryBuilder, $page, $maxPerPage);
    }

    /**
     * Creates Query Builder instance for all the records.
     *
     * @param string      $entityClass
     * @param string      $sortDirection
     * @param string|null $sortField
     * @param string|null $dqlFilter
     *
     * @return QueryBuilder The Query Builder instance
     */
    protected function createListQueryBuilder($entityClass, $sortDirection, $sortField = null, $dqlFilter = null)
    {
        return $this->get('harmonyadmin.query_builder')
            ->createListQueryBuilder($this->entity, $sortField, $sortDirection, $dqlFilter);
    }

    /**
     * Performs a database query based on the search query provided by the user.
     * It supports pagination and field sorting.
     *
     * @param string      $entityClass
     * @param string      $searchQuery
     * @param array       $searchableFields
     * @param int         $page
     * @param int         $maxPerPage
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @param string|null $dqlFilter
     *
     * @return Pagerfanta The paginated query results
     */
    protected function findBy($entityClass, $searchQuery, array $searchableFields, $page = 1, $maxPerPage = 15,
                              $sortField = null, $sortDirection = null, $dqlFilter = null)
    {
        if (empty($sortDirection) || !in_array(strtoupper($sortDirection), ['ASC', 'DESC'])) {
            $sortDirection = 'DESC';
        }
        $queryBuilder = $this->executeDynamicMethod('create<EntityName>SearchQueryBuilder',
            [$entityClass, $searchQuery, $searchableFields, $sortField, $sortDirection, $dqlFilter]);
        $this->dispatch(HarmonyAdminEvents::POST_SEARCH_QUERY_BUILDER, [
            'query_builder'     => $queryBuilder,
            'search_query'      => $searchQuery,
            'searchable_fields' => $searchableFields,
        ]);

        return $this->get('harmonyadmin.paginator')->createOrmPaginator($queryBuilder, $page, $maxPerPage);
    }

    /**
     * Creates Query Builder instance for search query.
     *
     * @param string      $entityClass
     * @param string      $searchQuery
     * @param array       $searchableFields
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @param string|null $dqlFilter
     *
     * @return QueryBuilder The Query Builder instance
     */
    protected function createSearchQueryBuilder($entityClass, $searchQuery, array $searchableFields, $sortField = null,
                                                $sortDirection = null, $dqlFilter = null)
    {
        return $this->get('harmonyadmin.query_builder')
            ->createSearchQueryBuilder($this->entity, $searchQuery, $sortField, $sortDirection, $dqlFilter);
    }

    /**
     * Creates the form used to edit an entity.
     *
     * @param object $entity
     * @param array  $entityProperties
     *
     * @return Form|FormInterface
     * @throws \Exception
     */
    protected function createEditForm($entity, array $entityProperties)
    {
        return $this->createEntityForm($entity, $entityProperties, 'edit');
    }

    /**
     * Creates the form used to create an entity.
     *
     * @param object $entity
     * @param array  $entityProperties
     *
     * @return Form|FormInterface
     * @throws \Exception
     */
    protected function createNewForm($entity, array $entityProperties)
    {
        return $this->createEntityForm($entity, $entityProperties, 'new');
    }

    /**
     * Creates the form builder of the form used to create or edit the given entity.
     *
     * @param object $entity
     * @param string $view The name of the view where this form is used ('new' or 'edit')
     *
     * @return FormBuilder
     */
    protected function createEntityFormBuilder($entity, $view)
    {
        $formOptions = $this->executeDynamicMethod('get<EntityName>EntityFormOptions', [$entity, $view]);

        return $this->get('form.factory')
            ->createNamedBuilder(mb_strtolower($this->entity['name']), FormTypeHelper::getTypeClass('harmonyadmin'),
                $entity, $formOptions);
    }

    /**
     * Retrieves the list of form options before sending them to the form builder.
     * This allows adding dynamic logic to the default form options.
     *
     * @param object $entity
     * @param string $view
     *
     * @return array
     */
    protected function getEntityFormOptions($entity, $view)
    {
        $formOptions           = $this->entity[$view]['form_options'];
        $formOptions['entity'] = $this->entity['name'];
        $formOptions['view']   = $view;

        return $formOptions;
    }

    /**
     * Creates the form object used to create or edit the given entity.
     *
     * @param object $entity
     * @param array  $entityProperties
     * @param string $view
     *
     * @return FormInterface
     * @throws \Exception
     */
    protected function createEntityForm($entity, array $entityProperties, $view)
    {
        if (method_exists($this, $customMethodName = 'create' . $this->entity['name'] . 'EntityForm')) {
            $form = $this->{$customMethodName}($entity, $entityProperties, $view);
            if (!$form instanceof FormInterface) {
                throw new \UnexpectedValueException(sprintf('The "%s" method must return a FormInterface, "%s" given.',
                    $customMethodName, \is_object($form) ? \get_class($form) : \gettype($form)));
            }

            return $form;
        }
        $formBuilder = $this->executeDynamicMethod('create<EntityName>EntityFormBuilder', [$entity, $view]);
        if (!$formBuilder instanceof FormBuilderInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s" method must return a FormBuilderInterface, "%s" given.',
                'createEntityForm', \is_object($formBuilder) ? \get_class($formBuilder) : \gettype($formBuilder)));
        }

        return $formBuilder->getForm();
    }

    /**
     * Creates the form used to delete an entity. It must be a form because
     * the deletion of the entity are always performed with the 'DELETE' HTTP method,
     * which requires a form to work in the current browsers.
     *
     * @param string     $entityName
     * @param int|string $entityId When reusing the delete form for multiple entities, a pattern string is passed
     *                             instead of an integer
     *
     * @return Form|FormInterface
     */
    protected function createDeleteForm($entityName, $entityId)
    {
        /** @var FormBuilder $formBuilder */
        $formBuilder = $this->get('form.factory')
            ->createNamedBuilder('delete_form')
            ->setAction($this->generateUrl('admin', ['action' => 'delete', 'entity' => $entityName, 'id' => $entityId]))
            ->setMethod('DELETE');
        $formBuilder->add('submit', FormTypeHelper::getTypeClass('submit'),
            ['label' => 'delete_modal.action', 'translation_domain' => 'HarmonyAdminBundle']);
        // needed to avoid submitting empty delete forms (see issue #1409)
        $formBuilder->add('_harmonyadmin_delete_flag', FormTypeHelper::getTypeClass('hidden'), ['data' => '1']);

        return $formBuilder->getForm();
    }

    /**
     * Utility method that checks if the given action is allowed for
     * the current entity.
     *
     * @param string $actionName
     *
     * @return bool
     */
    protected function isActionAllowed($actionName)
    {
        return false === \in_array($actionName, $this->entity['disabled_actions'], true);
    }

    /**
     * Given a method name pattern, it looks for the customized version of that
     * method (based on the entity name) and executes it. If the custom method
     * does not exist, it executes the regular method.
     * For example:
     *   executeDynamicMethod('create<EntityName>Entity') and the entity name is 'User'
     *   if 'createUserEntity()' exists, execute it; otherwise execute 'createEntity()'
     *
     * @param string $methodNamePattern The pattern of the method name (dynamic parts are enclosed with <> angle
     *                                  brackets)
     * @param array  $arguments         The arguments passed to the executed method
     *
     * @return mixed
     */
    protected function executeDynamicMethod($methodNamePattern, array $arguments = [])
    {
        $methodName = str_replace('<EntityName>', $this->entity['name'], $methodNamePattern);
        if (!\is_callable([$this, $methodName])) {
            $methodName = str_replace('<EntityName>', '', $methodNamePattern);
        }

        return \call_user_func_array([$this, $methodName], $arguments);
    }

    /**
     * @return Response
     * @throws \ErrorException
     */
    protected function redirectToReferrer(): Response
    {
        $refererUrl    = $this->request->query->get('referer', '');
        $refererAction = $this->request->query->get('action');
        // 1. redirect to list if possible
        if ($this->isActionAllowed('list')) {
            if (!empty($refererUrl)) {
                return $this->redirect(urldecode($refererUrl));
            }

            return $this->redirectToRoute('admin', [
                'action'       => 'list',
                'entity'       => $this->entity['name'],
                'menuIndex'    => $this->request->query->get('menuIndex'),
                'submenuIndex' => $this->request->query->get('submenuIndex'),
            ]);
        }
        // 2. from new|edit action, redirect to edit if possible
        if (\in_array($refererAction, ['new', 'edit']) && $this->isActionAllowed('edit')) {
            return $this->redirectToRoute('admin', [
                'action'       => 'edit',
                'entity'       => $this->entity['name'],
                'menuIndex'    => $this->request->query->get('menuIndex'),
                'submenuIndex' => $this->request->query->get('submenuIndex'),
                'id'           => ('new' === $refererAction) ? PropertyAccess::createPropertyAccessor()
                    ->getValue($this->request->attributes->get('harmonyadmin')['item'],
                        $this->entity['primary_key_field_name']) : $this->request->query->get('id'),
            ]);
        }
        // 3. from new action, redirect to new if possible
        if ('new' === $refererAction && $this->isActionAllowed('new')) {
            return $this->redirectToRoute('admin', [
                'action'       => 'new',
                'entity'       => $this->entity['name'],
                'menuIndex'    => $this->request->query->get('menuIndex'),
                'submenuIndex' => $this->request->query->get('submenuIndex'),
            ]);
        }

        return $this->redirectToBackendHomepage();
    }

    /**
     * Used to add/modify/remove parameters before passing them to the Twig template.
     * Instead of defining a render method per action (list, show, search, etc.) use
     * the $actionName argument to discriminate between actions.
     *
     * @param string $actionName   The name of the current action (list, show, new, etc.)
     * @param string $templatePath The path of the Twig template to render
     * @param array  $parameters   The parameters passed to the template
     *
     * @return Response
     */
    protected function renderTemplate($actionName, $templatePath, array $parameters = [])
    {
        return $this->render($templatePath, $parameters);
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
     */
    protected function getBlockCount($class, $dql_filter)
    {
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