<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Manager\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

    /** @var SettingsManager $settingsManager */
    protected $settingsManager;

    /** @var ParameterBagInterface $parameterBag */
    protected $parameterBag;

    /**
     * ThemeController constructor.
     *
     * @param SettingsManager       $settingsManager
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(SettingsManager $settingsManager, ParameterBagInterface $parameterBag)
    {
        $this->settingsManager = $settingsManager;
        $this->parameterBag    = $parameterBag;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        $themes = [];
        foreach ($this->parameterBag->get('kernel.themes') as $name => $theme) {
            // TODO: retrieve object from Kernel without creating a new one
            $themes[$name] = new $theme;
        }

        return $this->render('@HarmonyAdmin\theme\index.html.twig', ['themes' => $themes]);
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
        if (true === $this->settingsManager->save($themeSetting)) {
            $this->addFlash('success', 'Theme ' . $name . ' successfully activated');
        } else {
            $this->addFlash('danger', 'Oups!!! Something went wrong');
        }

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
        if (true === $this->settingsManager->delete($themeSetting)) {
            $this->addFlash('success', 'Theme ' . $name . ' successfully deactivated');
        } else {
            $this->addFlash('danger', 'Oups!!! Something went wrong');
        }

        return $this->redirectToRoute('admin_theme_index');
    }
}