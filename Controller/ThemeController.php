<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Manager\SettingsManager;
use Harmony\Bundle\ThemeBundle\Locator\ThemeLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ThemeController
 * @Route("/theme", name="admin_theme_")
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class ThemeController extends AbstractController
{

    /** @var ThemeLocator $themeLocator */
    protected $themeLocator;

    /** @var SettingsManager $settingsManager */
    protected $settingsManager;

    /**
     * ThemeController constructor.
     *
     * @param ThemeLocator    $themeLocator
     * @param SettingsManager $settingsManager
     */
    public function __construct(ThemeLocator $themeLocator, SettingsManager $settingsManager)
    {
        $this->themeLocator    = $themeLocator;
        $this->settingsManager = $settingsManager;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     * @throws \Harmony\Bundle\ThemeBundle\Json\JsonValidationException
     * @throws \Seld\JsonLint\ParsingException
     */
    public function index(): Response
    {
        return $this->render('@HarmonyAdmin\theme\index.html.twig',
            ['themes' => $this->themeLocator->discoverThemes()]);
    }

    /**
     * @Route("/activate/{name}", name="activate")
     * @param string $name
     *
     * @return Response
     */
    public function activate(string $name): Response
    {
        $themeSetting = $this->settingsManager->getSetting('theme');
        $themeSetting->setData($name);
        $this->settingsManager->save($themeSetting);

        return $this->redirectToRoute('admin_theme_index');
    }

    /**
     * @Route("/deactivate/{name}", name="deactivate")
     * @param string $name
     *
     * @return Response
     */
    public function deactivate(string $name): Response
    {
        $themeSetting = $this->settingsManager->getSetting('theme');
        $themeSetting->setData($name);
        $this->settingsManager->delete($themeSetting);

        return $this->redirectToRoute('admin_theme_index');
    }
}