<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Harmony\Bundle\AdminBundle\Form\Type\ContainerExtensionType;
use Harmony\Bundle\CoreBundle\Model\ConfigInterface;
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

    /** @var ManagerRegistry $registry */
    protected $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('@HarmonyAdmin\settings\config.html.twig', [
            'config' => $this->registry->getRepository(ConfigInterface::class)->findAll()
        ]);
    }

    /**
     * @Route("/add", name="add")
     * @param Request $request
     *
     * @return Response
     */
    public function add(Request $request): Response
    {
        $form = $this->createForm(ContainerExtensionType::class);
        $form->handleRequest($request);

        return $this->render('@HarmonyAdmin\settings\config_add.html.twig', ['form' => $form->createView()]);
    }

    public function addExtensionParameters(Request $request, string $alias): Response
    {
    }

}