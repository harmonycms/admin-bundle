<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Form\Type\SettingsType;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
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
     * @Route("/settings/{domainName}", name="settings", defaults={"domainName"="default"})
     * @param Request $request
     * @param string  $domainName
     *
     * @return Response
     */
    public function index(Request $request, string $domainName): Response
    {
        $this->initialize($request);
        $settings = $this->settingsManager->getSettingsByDomain([$domainName]);

        $form = $this->createForm(SettingsType::class, ['settings' => $settings]);
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