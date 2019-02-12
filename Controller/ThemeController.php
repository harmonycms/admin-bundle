<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\CoreBundle\Manager\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
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

    /** @var AbstractKernel|KernelInterface $kernel */
    protected $kernel;

    /**
     * ThemeController constructor.
     *
     * @param SettingsManager                $settingsManager
     * @param KernelInterface|AbstractKernel $kernel
     */
    public function __construct(SettingsManager $settingsManager, KernelInterface $kernel)
    {
        $this->settingsManager = $settingsManager;
        $this->kernel          = $kernel;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        $themes = [];
        foreach ($this->kernel->getThemes() as $name => $theme) {
            $themes[$name] = $theme;
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