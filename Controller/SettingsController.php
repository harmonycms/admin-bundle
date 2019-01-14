<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use FOS\UserBundle\Model\UserInterface;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Helis\SettingsManagerBundle\Validator\Constraints\SettingType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SettingsController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class SettingsController extends AbstractController
{

    use InitializeTrait;

    /** @var SettingsManager $settingsManager */
    protected $settingsManager;

    /**
     * SettingsController constructor.
     *
     * @param SettingsManager $settingsManager
     */
    public function __construct(SettingsManager $settingsManager)
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
        $form = $this->createForm(SettingType::class, $this->settingsManager->all($user));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsManager->setMany($form->getData(), $user);

            return $this->redirect($request->getUri());
        }

        return $this->render('@HarmonyAdmin\settings\index.html.twig', ['form' => $form->createView()]);
    }
}