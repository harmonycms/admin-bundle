<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\DependencyInjection\HarmonyCoreExtension;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AdminController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class AdminController extends Controller
{

    /**
     * @return Response
     * @throws \ErrorException
     */
    public function index(): Response
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

        return $this->render('@HarmonyAdmin\Default\index.html.twig', [
            'dashboard' => $dashboard
        ]);
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