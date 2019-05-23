<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Harmony\Bundle\CoreBundle\Model\ParameterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SettingsController
 * @Route("/settings", name="admin_settings_")
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class SettingsController extends AbstractController
{

    /** @var ManagerRegistry $registry */
    protected $registry;

    /**
     * SettingsController constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @Route("/parameters", name="parameters")
     * @return Response
     */
    public function parameters(): Response
    {
        return $this->render('@HarmonyAdmin\settings\parameters.html.twig', [
            'parameters' => $this->registry->getRepository(ParameterInterface::class)->findAll()
        ]);
    }
}