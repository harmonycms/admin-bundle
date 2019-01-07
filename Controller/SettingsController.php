<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\AdminBundle\Form\Type\SettingsType;
use Harmony\Bundle\AdminBundle\Manager\SettingsManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class SettingsController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class SettingsController extends AbstractController
{

    use InitializeTrait;

    /** @var SettingsManagerInterface $settingsManager */
    protected $settingsManager;

    /**
     * SettingsController constructor.
     *
     * @param SettingsManagerInterface $settingsManager
     */
    public function __construct(SettingsManagerInterface $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @Route("/settings", name="settings")
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request): Response
    {
        $this->initialize($request);

        return $this->manage($request);
    }

    /**
     * @param Request            $request
     * @param UserInterface|null $user
     *
     * @return Response
     */
    protected function manage(Request $request, UserInterface $user = null): Response
    {
        $form = $this->createForm(SettingsType::class, $this->settingsManager->all($user));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

        }

        return $this->render('@HarmonyAdmin\settings\index.html.twig', ['form' => $form->createView()]);
    }
}