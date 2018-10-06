<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use EasyCorp\Bundle\EasyAdminBundle\Exception\UndefinedEntityException;
use Harmony\Bundle\CoreBundle\DependencyInjection\HarmonyCoreExtension;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AdminController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class AdminController extends BaseAdminController
{

    /**
     * @return Response
     * @throws \ErrorException
     */
    protected function redirectToBackendHomepage(): Response
    {
        $dashboard = $this->container->getParameter(HarmonyCoreExtension::ALIAS . '.dashboard');
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
     * @param Request $request
     */
    protected function initialize(Request $request)
    {
        $this->dispatch(EasyAdminEvents::PRE_INITIALIZE);

        $this->config = $this->get('easyadmin.config.manager')->getBackendConfig();

        // this condition happens when accessing the backend homepage and before
        // redirecting to the default page set as the homepage
        if (null === $entityName = $request->query->get('entity')) {
            return;
        }

        if (!array_key_exists($entityName, $this->config['entities'])) {
            throw new UndefinedEntityException(['entity_name' => $entityName]);
        }

        $this->entity = $this->get('easyadmin.config.manager')->getEntityConfiguration($entityName);

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

        $this->dispatch(EasyAdminEvents::POST_INITIALIZE);
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