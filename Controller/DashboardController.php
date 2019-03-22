<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DashboardController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class DashboardController extends AbstractController
{

    /** @var array $configAdmin */
    protected $configAdmin;

    /**
     * DashboardController constructor.
     *
     * @param array $configAdmin
     */
    public function __construct(array $configAdmin)
    {
        $this->configAdmin = $configAdmin;
    }

    /**
     * @Route("/", name="admin")
     * @return Response
     */
    public function index(): Response
    {
        $dashboard = $this->configAdmin['dashboard'];
//        if (!empty($dashboard['blocks'])) {
//            foreach ($dashboard['blocks'] as $key => $block) {
//                if (!empty($block['items'])) {
//                    foreach ($block['items'] as $k => $item) {
//                        if (!empty($item['query'])) {
//                            $count = $this->executeCustomQuery($item['class'], $item['query']);
//                        } else {
//                            $count = $this->getBlockCount($item['class'],
//                                !empty($item['dql_filter']) ? $item['dql_filter'] : false);
//                        }
//                        $dashboard['blocks'][$key]['items'][$k]['count'] = $count;
//
//                        if (!empty($item['model'])) {
//                            $model = $item['model'];
//                        } else {
//                            $model = $this->guessModelFromClass($item['class']);
//                        }
//                        $dashboard['blocks'][$key]['items'][$k]['model'] = $model;
//                    }
//                }
//            }
//        }

        return $this->render('@HarmonyAdmin\dashboard\index.html.twig', [
            'dashboard' => $dashboard
        ]);
    }
}