<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Helis\SettingsManagerBundle\Form\SettingFormType;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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
        $settings = $this->settingsManager->getSettingsByDomain(['default']);

        $form = $this->createFormBuilder(['settings' => $settings]);
        $form->add('settings', CollectionType::class, [
            'entry_type' => SettingFormType::class,
        ]);
        $form->add('edit', SubmitType::class);
        $form = $form->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            foreach ($data['settings'] as $key => $setting) {
                $this->settingsManager->update($setting);
            }

            return $this->redirectToRoute('settings');
        }

        return $this->render('@HarmonyAdmin\settings\index.html.twig', [
            'form' => $form->createView()
        ]);
    }
}