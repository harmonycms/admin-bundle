<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    /** @var TranslatorInterface $translator */
    protected $translator;

    /**
     * ThemeController constructor.
     *
     * @param SettingsManager                $settingsManager
     * @param KernelInterface|AbstractKernel $kernel
     * @param TranslatorInterface            $translator
     */
    public function __construct(SettingsManager $settingsManager, KernelInterface $kernel,
                                TranslatorInterface $translator)
    {
        $this->settingsManager = $settingsManager;
        $this->kernel          = $kernel;
        $this->translator      = $translator;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('@HarmonyAdmin\theme\index.html.twig', [
            'themes'  => $this->kernel->getThemes(),
            'setting' => $this->settingsManager->getSetting('theme')
        ]);
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
            $this->addFlash('success',
                $this->translator->trans('theme.activated_success', ['%name%' => $name], 'HarmonyAdminBundle'));
        } else {
            $this->addFlash('danger', $this->translator->trans('theme.error_message', [], 'HarmonyAdminBundle'));
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
            $this->addFlash('success',
                $this->translator->trans('theme.deactivated_success', ['%name%' => $name], 'HarmonyAdminBundle'));
        } else {
            $this->addFlash('danger', $this->translator->trans('theme.error_message', [], 'HarmonyAdminBundle'));
        }

        return $this->redirectToRoute('admin_theme_index');
    }
}