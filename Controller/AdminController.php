<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\AdminBundle\Event\HarmonyAdminEvents;
use Harmony\Bundle\AdminBundle\Exception\ForbiddenActionException;
use Harmony\Bundle\AdminBundle\Exception\ModelRemoveException;
use Harmony\Bundle\AdminBundle\Form\Util\FormTypeHelper;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
class AdminController extends AbstractController
{

    use InitializeTrait;

    /**
     * @Route("/model/{model}/{action}", name="admin_model")
     * @param Request $request
     * @param string  $model
     * @param string  $action
     *
     * @return Response
     */
    public function index(Request $request, string $model, string $action = 'list'): Response
    {
        $this->initialize($request, $model);

        return $this->executeDynamicMethod($action . '<ModelName>');
    }

    /**
     * @Route("/model/{model}/{action}", name="admin_model")
     * @param Request $request
     * @param string  $model
     * @param string  $action
     *
     * @return Response
     */
    public function model(Request $request, string $model, string $action = 'list'): Response
    {
        $this->initialize($request, $model);
        if (!$this->isActionAllowed($action)) {
            throw new ForbiddenActionException(['action' => $action, 'model_name' => $this->model['name']]);
        }

        return $this->executeDynamicMethod($action . '<ModelName>');
    }

    /**
     * The method that returns the values displayed by an autocomplete field
     * based on the user's input.
     *
     * @return JsonResponse
     */
    protected function autocomplete(): JsonResponse
    {
        $results = $this->get('harmony_admin.autocomplete')
            ->find($this->request->query->get('model'), $this->request->query->get('query'),
                $this->request->query->get('page', 1));

        return new JsonResponse($results);
    }

    /**
     * The method that is executed when the user performs a 'list' action on an model.
     *
     * @return Response
     */
    protected function list(): Response
    {
        $this->dispatch(HarmonyAdminEvents::PRE_LIST);
        $fields    = $this->model['list']['fields'];
        $paginator = $this->findAll($this->model['class'], $this->request->query->get('page', 1),
            $this->model['list']['max_results'], $this->request->query->get('sortField'),
            $this->request->query->get('sortDirection'), $this->model['list']['dql_filter']);
        $this->dispatch(HarmonyAdminEvents::POST_LIST, ['paginator' => $paginator]);
        $parameters = [
            'paginator'            => $paginator,
            'fields'               => $fields,
            'delete_form_template' => $this->createDeleteForm($this->model['name'], '__id__')->createView(),
        ];

        return $this->executeDynamicMethod('render<ModelName>Template',
            ['list', $this->model['templates']['list'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'edit' action on an model.
     *
     * @return Response|RedirectResponse
     * @throws \RuntimeException
     * @throws \ErrorException
     */
    protected function edit()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_EDIT);
        $id           = $this->request->query->get('id');
        $harmonyAdmin = $this->request->attributes->get('harmony_admin');
        $item         = $harmonyAdmin['item'];
        if ($this->request->isXmlHttpRequest() && $property = $this->request->query->get('property')) {
            $newValue       = 'true' === mb_strtolower($this->request->query->get('newValue'));
            $fieldsMetadata = $this->model['list']['fields'];
            if (!isset($fieldsMetadata[$property]) || 'toggle' !== $fieldsMetadata[$property]['dataType']) {
                throw new \RuntimeException(sprintf('The type of the "%s" property is not "toggle".', $property));
            }
            $this->updateModelProperty($item, $property, $newValue);

            // cast to integer instead of string to avoid sending empty responses for 'false'
            return new Response((int)$newValue);
        }
        $fields     = $this->model['edit']['fields'];
        $editForm   = $this->executeDynamicMethod('create<ModelName>EditForm', [$item, $fields]);
        $deleteForm = $this->createDeleteForm($this->model['name'], $id);
        $editForm->handleRequest($this->request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->dispatch(HarmonyAdminEvents::PRE_UPDATE, ['model' => $item]);
            $this->executeDynamicMethod('update<ModelName>Model', [$item, $editForm]);
            $this->dispatch(HarmonyAdminEvents::POST_UPDATE, ['model' => $item]);

            return $this->redirectToReferrer();
        }
        $this->dispatch(HarmonyAdminEvents::POST_EDIT);
        $parameters = [
            'form'         => $editForm->createView(),
            'model_fields' => $fields,
            'model'        => $item,
            'delete_form'  => $deleteForm->createView(),
        ];

        return $this->executeDynamicMethod('render<ModelName>Template',
            ['edit', $this->model['templates']['edit'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'show' action on an model.
     *
     * @return Response
     */
    protected function show()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_SHOW);
        $id           = $this->request->query->get('id');
        $harmonyAdmin = $this->request->attributes->get('harmony_admin');
        $item         = $harmonyAdmin['item'];
        $fields       = $this->model['show']['fields'];
        $deleteForm   = $this->createDeleteForm($this->model['name'], $id);
        $this->dispatch(HarmonyAdminEvents::POST_SHOW, [
            'deleteForm' => $deleteForm,
            'fields'     => $fields,
            'model'      => $item,
        ]);
        $parameters = [
            'model'       => $item,
            'fields'      => $fields,
            'delete_form' => $deleteForm->createView(),
        ];

        return $this->executeDynamicMethod('render<ModelName>Template',
            ['show', $this->model['templates']['show'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'new' action on an model.
     *
     * @return Response|RedirectResponse
     * @throws \ErrorException
     */
    protected function new()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_NEW);
        $model                = $this->executeDynamicMethod('createNew<ModelName>Model');
        $harmonyAdmin         = $this->request->attributes->get('harmony_admin');
        $harmonyAdmin['item'] = $model;
        $this->request->attributes->set('harmony_admin', $harmonyAdmin);
        $fields  = $this->model['new']['fields'];
        $newForm = $this->executeDynamicMethod('create<ModelName>NewForm', [$model, $fields]);
        $newForm->handleRequest($this->request);
        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->dispatch(HarmonyAdminEvents::PRE_PERSIST, ['model' => $model]);
            $this->executeDynamicMethod('persist<ModelName>Model', [$model, $newForm]);
            $this->dispatch(HarmonyAdminEvents::POST_PERSIST, ['model' => $model]);

            return $this->redirectToReferrer();
        }
        $this->dispatch(HarmonyAdminEvents::POST_NEW, [
            'model_fields' => $fields,
            'form'         => $newForm,
            'model'        => $model,
        ]);
        $parameters = [
            'form'         => $newForm->createView(),
            'model_fields' => $fields,
            'model'        => $model,
        ];

        return $this->executeDynamicMethod('render<ModelName>Template',
            ['new', $this->model['templates']['new'], $parameters]);
    }

    /**
     * The method that is executed when the user performs a 'delete' action to
     * remove any model.
     *
     * @return Response
     * @throws ModelRemoveException
     * @throws \ErrorException
     */
    protected function delete()
    {
        $this->dispatch(HarmonyAdminEvents::PRE_DELETE);
        if ('DELETE' !== $this->request->getMethod()) {
            return $this->redirect($this->generateUrl('admin', ['action' => 'list', 'model' => $this->model['name']]));
        }
        $id   = $this->request->query->get('id');
        $form = $this->createDeleteForm($this->model['name'], $id);
        $form->handleRequest($this->request);
        if ($form->isSubmitted() && $form->isValid()) {
            $harmonyAdmin = $this->request->attributes->get('harmony_admin');
            $item         = $harmonyAdmin['item'];
            $this->dispatch(HarmonyAdminEvents::PRE_REMOVE, ['model' => $item]);
            try {
                $this->executeDynamicMethod('remove<ModelName>Model', [$item, $form]);
            }
            catch (\Exception $e) {
                throw new ModelRemoveException([
                    'model_name' => $this->model['name'],
                    'message'    => $e->getMessage()
                ]);
            }
            $this->dispatch(HarmonyAdminEvents::POST_REMOVE, ['model' => $item]);
        }
        $this->dispatch(HarmonyAdminEvents::POST_DELETE);

        return $this->redirectToReferrer();
    }

    /**
     * The method that is executed when the user performs a query on an model.
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
        $searchableFields     = $this->model['search']['fields'];
        $defaultSortField     = isset($this->model['search']['sort']['field']) ?
            $this->model['search']['sort']['field'] : null;
        $defaultSortDirection = isset($this->model['search']['sort']['direction']) ?
            $this->model['search']['sort']['direction'] : null;
        $paginator            = $this->findBy($this->model['class'], $query, $searchableFields,
            $this->request->query->get('page', 1), $this->model['list']['max_results'],
            $this->request->query->get('sortField', $defaultSortField),
            $this->request->query->get('sortDirection', $defaultSortDirection), $this->model['search']['dql_filter']);
        $fields               = $this->model['list']['fields'];
        $this->dispatch(HarmonyAdminEvents::POST_SEARCH, [
            'fields'    => $fields,
            'paginator' => $paginator,
        ]);
        $parameters = [
            'paginator'            => $paginator,
            'fields'               => $fields,
            'delete_form_template' => $this->createDeleteForm($this->model['name'], '__id__')->createView(),
        ];

        return $this->executeDynamicMethod('render<ModelName>Template',
            ['search', $this->model['templates']['list'], $parameters]);
    }

    /**
     * It updates the value of some property of some model to the new given value.
     *
     * @param mixed  $model    The instance of the model to modify
     * @param string $property The name of the property to change
     * @param bool   $value    The new value of the property
     *
     * @throws \RuntimeException
     */
    protected function updateModelProperty($model, $property, $value)
    {
        $modelConfig = $this->model;
        if (!$this->get('harmony_admin.property_accessor')->isWritable($model, $property)) {
            throw new \RuntimeException(sprintf('The "%s" property of the "%s" model is not writable.', $property,
                $modelConfig['name']));
        }
        $this->get('harmony_admin.property_accessor')->setValue($model, $property, $value);
        $this->dispatch(HarmonyAdminEvents::PRE_UPDATE, ['model' => $model, 'newValue' => $value]);
        $this->executeDynamicMethod('update<ModelName>Model', [$model]);
        $this->dispatch(HarmonyAdminEvents::POST_UPDATE, ['model' => $model, 'newValue' => $value]);
        $this->dispatch(HarmonyAdminEvents::POST_EDIT);
    }

    /**
     * Creates a new object of the current managed model.
     * This method is mostly here for override convenience, because it allows
     * the user to use his own method to customize the model instantiation.
     *
     * @return object
     */
    protected function createNewModel()
    {
        $modelFullyQualifiedClassName = $this->model['class'];

        return new $modelFullyQualifiedClassName();
    }

    /**
     * Allows applications to modify the model associated with the item being
     * created while persisting it.
     *
     * @param object $model
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function persistModel($model)
    {
        $this->objectManager->persist($model);
        $this->objectManager->flush();
    }

    /**
     * Allows applications to modify the model associated with the item being
     * edited before updating it.
     *
     * @param object $model
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function updateModel($model)
    {
        $this->objectManager->persist($model);
        $this->objectManager->flush();
    }

    /**
     * Allows applications to modify the model associated with the item being
     * deleted before removing it.
     *
     * @param object $model
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function removeModel($model)
    {
        $this->objectManager->remove($model);
        $this->objectManager->flush();
    }

    /**
     * Performs a database query to get all the records related to the given
     * model. It supports pagination and field sorting.
     *
     * @param string      $class
     * @param int         $page
     * @param int         $maxPerPage
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @param string|null $dqlFilter
     *
     * @return Pagerfanta The paginated query results
     */
    protected function findAll($class, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null,
                               $dqlFilter = null)
    {
        if (!\in_array(strtoupper($sortDirection), ['ASC', 'DESC'])) {
            $sortDirection = 'DESC';
        }
        $queryBuilder = $this->executeDynamicMethod('create<ModelName>ListQueryBuilder',
            [$class, $sortDirection, $sortField, $dqlFilter]);
        $this->dispatch(HarmonyAdminEvents::POST_LIST_QUERY_BUILDER, [
            'query_builder'  => $queryBuilder,
            'sort_field'     => $sortField,
            'sort_direction' => $sortDirection,
        ]);

        return $this->searchPaginator->createPaginator($queryBuilder, $page, $maxPerPage);
    }

    /**
     * Creates Query Builder instance for all the records.
     *
     * @param string      $class
     * @param string      $sortDirection
     * @param string|null $sortField
     * @param string|null $dqlFilter
     *
     * @return \Doctrine\MongoDB\Query\Builder|\Doctrine\ORM\QueryBuilder Query Builder instance
     */
    protected function createListQueryBuilder($class, $sortDirection, $sortField = null, $dqlFilter = null)
    {
        return $this->builderRegistry->createListBuilder($class, $sortField, $sortDirection, $dqlFilter);
    }

    /**
     * Performs a database query based on the search query provided by the user.
     * It supports pagination and field sorting.
     *
     * @param string      $class
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
    protected function findBy($class, $searchQuery, array $searchableFields, $page = 1, $maxPerPage = 15,
                              $sortField = null, $sortDirection = null, $dqlFilter = null)
    {
        if (empty($sortDirection) || !in_array(strtoupper($sortDirection), ['ASC', 'DESC'])) {
            $sortDirection = 'DESC';
        }
        $queryBuilder = $this->executeDynamicMethod('create<ModelName>SearchQueryBuilder',
            [$class, $searchQuery, $searchableFields, $sortField, $sortDirection, $dqlFilter]);
        $this->dispatch(HarmonyAdminEvents::POST_SEARCH_QUERY_BUILDER, [
            'query_builder'     => $queryBuilder,
            'search_query'      => $searchQuery,
            'searchable_fields' => $searchableFields,
        ]);

        return $this->searchPaginator->createPaginator($queryBuilder, $page, $maxPerPage);
    }

    /**
     * Creates Query Builder instance for search query.
     *
     * @param string      $class
     * @param string      $searchQuery
     * @param array       $searchableFields
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @param string|null $dqlFilter
     *
     * @return \Doctrine\ORM\QueryBuilder The Query Builder instance
     * @throws \MongoException
     */
    protected function createSearchQueryBuilder($class, $searchQuery, array $searchableFields, $sortField = null,
                                                $sortDirection = null, $dqlFilter = null)
    {
        return $this->builderRegistry->createSearchBuilder($this->model, $searchQuery, $sortField, $sortDirection,
            $dqlFilter);
    }

    /**
     * Creates the form used to edit an model.
     *
     * @param object $model
     * @param array  $properties
     *
     * @return Form|FormInterface
     * @throws \Exception
     */
    protected function createEditForm($model, array $properties)
    {
        return $this->createModelForm($model, $properties, 'edit');
    }

    /**
     * Creates the form used to create an model.
     *
     * @param object $model
     * @param array  $properties
     *
     * @return Form|FormInterface
     * @throws \Exception
     */
    protected function createNewForm($model, array $properties)
    {
        return $this->createModelForm($model, $properties, 'new');
    }

    /**
     * Creates the form builder of the form used to create or edit the given model.
     *
     * @param object $model
     * @param string $view The name of the view where this form is used ('new' or 'edit')
     *
     * @return FormBuilder
     */
    protected function createModelFormBuilder($model, $view)
    {
        $formOptions = $this->executeDynamicMethod('get<ModelName>ModelFormOptions', [$model, $view]);

        return $this->get('form.factory')
            ->createNamedBuilder(mb_strtolower($this->model['name']), FormTypeHelper::getTypeClass('harmony_admin'),
                $model, $formOptions);
    }

    /**
     * Retrieves the list of form options before sending them to the form builder.
     * This allows adding dynamic logic to the default form options.
     *
     * @param object $model
     * @param string $view
     *
     * @return array
     */
    protected function getModelFormOptions($model, $view)
    {
        $formOptions          = $this->model[$view]['form_options'];
        $formOptions['model'] = $this->model['name'];
        $formOptions['view']  = $view;

        return $formOptions;
    }

    /**
     * Creates the form object used to create or edit the given model.
     *
     * @param object $model
     * @param array  $properties
     * @param string $view
     *
     * @return FormInterface
     * @throws \Exception
     */
    protected function createModelForm($model, array $properties, $view)
    {
        if (method_exists($this, $customMethodName = 'create' . $this->model['name'] . 'ModelForm')) {
            $form = $this->{$customMethodName}($model, $properties, $view);
            if (!$form instanceof FormInterface) {
                throw new \UnexpectedValueException(sprintf('The "%s" method must return a FormInterface, "%s" given.',
                    $customMethodName, \is_object($form) ? \get_class($form) : \gettype($form)));
            }

            return $form;
        }
        $formBuilder = $this->executeDynamicMethod('create<ModelName>ModelFormBuilder', [$model, $view]);
        if (!$formBuilder instanceof FormBuilderInterface) {
            throw new \UnexpectedValueException(sprintf('The "%s" method must return a FormBuilderInterface, "%s" given.',
                'createModelForm', \is_object($formBuilder) ? \get_class($formBuilder) : \gettype($formBuilder)));
        }

        return $formBuilder->getForm();
    }

    /**
     * Creates the form used to delete an model. It must be a form because
     * the deletion of the model are always performed with the 'DELETE' HTTP method,
     * which requires a form to work in the current browsers.
     *
     * @param string     $ModelName
     * @param int|string $ModelId  When reusing the delete form for multiple models, a pattern string is passed
     *                             instead of an integer
     *
     * @return Form|FormInterface
     */
    protected function createDeleteForm($ModelName, $ModelId)
    {
        /** @var FormBuilder $formBuilder */
        $formBuilder = $this->get('form.factory')
            ->createNamedBuilder('delete_form')
            ->setAction($this->generateUrl('admin_model', [
                'action' => 'delete',
                'model'  => $ModelName,
                'id'     => $ModelId
            ]))
            ->setMethod('DELETE');
        $formBuilder->add('submit', FormTypeHelper::getTypeClass('submit'),
            ['label' => 'delete_modal.action', 'translation_domain' => 'HarmonyAdminBundle']);
        // needed to avoid submitting empty delete forms (see issue #1409)
        $formBuilder->add('_harmony_admin_delete_flag', FormTypeHelper::getTypeClass('hidden'), ['data' => '1']);

        return $formBuilder->getForm();
    }

    /**
     * Utility method that checks if the given action is allowed for
     * the current model.
     *
     * @param string $actionName
     *
     * @return bool
     */
    protected function isActionAllowed($actionName)
    {
        return false === \in_array($actionName, $this->model['disabled_actions'], true);
    }

    /**
     * Given a method name pattern, it looks for the customized version of that
     * method (based on the model name) and executes it. If the custom method
     * does not exist, it executes the regular method.
     * For example:
     *   executeDynamicMethod('create<ModelName>model') and the model name is 'User'
     *   if 'createUserModel()' exists, execute it; otherwise execute 'createModel()'
     *
     * @param string $methodNamePattern The pattern of the method name (dynamic parts are enclosed with <> angle
     *                                  brackets)
     * @param array  $arguments         The arguments passed to the executed method
     *
     * @return mixed
     */
    protected function executeDynamicMethod($methodNamePattern, array $arguments = [])
    {
        $methodName = str_replace('<ModelName>', $this->model['name'], $methodNamePattern);
        if (!\is_callable([$this, $methodName])) {
            $methodName = str_replace('<ModelName>', '', $methodNamePattern);
        }

        return \call_user_func_array([$this, $methodName], $arguments);
    }

    /**
     * @return Response
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
                'model'        => $this->model['name'],
                'menuIndex'    => $this->request->query->get('menuIndex'),
                'submenuIndex' => $this->request->query->get('submenuIndex'),
            ]);
        }
        // 2. from new|edit action, redirect to edit if possible
        if (\in_array($refererAction, ['new', 'edit']) && $this->isActionAllowed('edit')) {
            return $this->redirectToRoute('admin', [
                'action'       => 'edit',
                'model'        => $this->model['name'],
                'menuIndex'    => $this->request->query->get('menuIndex'),
                'submenuIndex' => $this->request->query->get('submenuIndex'),
                'id'           => ('new' === $refererAction) ? PropertyAccess::createPropertyAccessor()
                    ->getValue($this->request->attributes->get('harmony_admin')['item'],
                        $this->model['primary_key_field_name']) : $this->request->query->get('id'),
            ]);
        }
        // 3. from new action, redirect to new if possible
        if ('new' === $refererAction && $this->isActionAllowed('new')) {
            return $this->redirectToRoute('admin', [
                'action'       => 'new',
                'model'        => $this->model['name'],
                'menuIndex'    => $this->request->query->get('menuIndex'),
                'submenuIndex' => $this->request->query->get('submenuIndex'),
            ]);
        }

        return $this->redirectToRoute('admin');
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
}