<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\AdminBundle\Exception\ForbiddenActionException;
use Harmony\Bundle\CoreBundle\DependencyInjection\HarmonyCoreExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DashboardController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class DashboardController extends AbstractController
{

    use InitializeTrait;

    /**
     * @Route("/", name="admin")
     * @param Request $request
     *
     * @return Response
     * @throws ForbiddenActionException
     * @throws \ErrorException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function index(Request $request): Response
    {
        $this->initialize($request);

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

        return $this->render('@HarmonyAdmin\dashboard\index.html.twig', [
            'dashboard' => $dashboard
        ]);
    }
}