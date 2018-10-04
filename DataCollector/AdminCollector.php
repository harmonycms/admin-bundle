<?php

namespace Harmony\Bundle\AdminBundle\DataCollector;

use Harmony\Bundle\WebProfilerBundle\DataCollector\AbstractHarmonyCollector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AdminCollector
 *
 * @package Harmony\Bundle\AdminBundle\DataCollector
 */
class AdminCollector extends AbstractHarmonyCollector
{

    /**
     * @return Response
     */
    public function getToolbar(): Response
    {
        return $this->render('@HarmonyAdmin/Collector/toolbar.html.twig');
    }
}