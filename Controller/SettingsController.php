<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Form\Type\SettingsType;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
     * @Route("/{domainName}/{tagName}", name="index", defaults={"domainName"="default",
     *     "tagName"="general"})
     * @param Request $request
     * @param string  $domainName
     * @param string  $tagName
     *
     * @return Response
     */
    public function index(Request $request, string $domainName, string $tagName): Response
    {
        $this->initialize($request);
        $settings = $this->settingsManager->getEnabledSettingsByTag([$domainName], $tagName);

        $form = $this->createForm(SettingsType::class, ['settings' => $settings]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var SettingModel $setting */
            foreach ($data['settings'] as $setting) {
                if (null !== $setting->getData()) {
                    $this->settingsManager->save($setting);
                }
            }

            $this->addFlash('success', 'Settings has been updated successfully.');

            return $this->redirectToRoute('settings', array_merge([
                'domainName' => $domainName,
                'tagName'    => $tagName
            ], $request->query->all()));
        }

        return $this->render('@HarmonyAdmin\settings\index.html.twig', [
            'form' => $form->createView(),
            'tag'  => $tagName
        ]);
    }
}