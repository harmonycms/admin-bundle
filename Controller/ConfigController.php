<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\AdminBundle\Form\Type\ContainerExtensionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ConfigController
 * @Route("/settings/config", name="admin_settings_config_")
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class ConfigController extends AbstractController
{

    /**
     * @Route("/", name="index")
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request): Response
    {
        $form = $this->createForm(ContainerExtensionType::class);
        $form->handleRequest($request);

        return $this->render('@HarmonyAdmin\settings\config.html.twig', ['form' => $form->createView()]);
    }

}