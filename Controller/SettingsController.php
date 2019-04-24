<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\SettingsManagerBundle\Form\Type\SettingsType;
use Harmony\Bundle\SettingsManagerBundle\Model\Setting;
use Harmony\Bundle\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use function array_merge;

/**
 * Class SettingsController
 * @Route("/settings", name="admin_settings_")
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class SettingsController extends AbstractController
{

    /** @var SettingsManager $settingsManager */
    protected $settingsManager;

    /** @var TranslatorInterface $translator */
    protected $translator;

    /** @var KernelInterface $kernel */
    protected $kernel;

    /**
     * SettingsController constructor.
     *
     * @param SettingsManager                $settingsManager
     * @param TranslatorInterface            $translator
     * @param KernelInterface|AbstractKernel $kernel
     */
    public function __construct(SettingsManager $settingsManager, TranslatorInterface $translator,
                                KernelInterface $kernel)
    {
        $this->settingsManager = $settingsManager;
        $this->translator      = $translator;
        $this->kernel          = $kernel;
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
        $settings = $this->settingsManager->getEnabledSettingsByTag([$domainName], $tagName);

        $transDomain = 'HarmonyAdminBundle';
        if ('theme' === $domainName) {
            $themeSetting = $this->settingsManager->getSetting('theme');
            $theme        = $this->kernel->getTheme($themeSetting->getData());
            $transDomain  = $theme->getTransDomain();
        }

        $form = $this->createForm(SettingsType::class, ['settings' => $settings], [
            'translation_domain' => $transDomain
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var Setting $setting */
            foreach ($data['settings'] as $setting) {
                if (null !== $setting->getData()) {
                    $this->settingsManager->save($setting);
                }
            }

            $this->addFlash('success', $this->translator->trans('setting.updated_success', [], 'HarmonyAdminBundle'));

            return $this->redirectToRoute('admin_settings_index', array_merge([
                'domainName' => $domainName,
                'tagName'    => $tagName
            ], $request->query->all()));
        }

        return $this->render('@HarmonyAdmin\settings\index.html.twig', [
            'form'         => $form->createView(),
            'tag'          => $tagName,
            'trans_domain' => $transDomain
        ]);
    }
}