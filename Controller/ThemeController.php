<?php

namespace Harmony\Bundle\AdminBundle\Controller;

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

    /**
     * ThemeController constructor.
     *
     * @param ThemeLocator $themeLocator
     */
    public function __construct(ThemeLocator $themeLocator)
    {
        $this->themeLocator = $themeLocator;
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
}