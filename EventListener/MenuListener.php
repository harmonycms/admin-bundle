<?php

namespace Harmony\Bundle\AdminBundle\EventListener;

use Harmony\Bundle\CoreBundle\Event\ConfigureMenuEvent;
use Helis\SettingsManagerBundle\Settings\SettingsRouter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class MenuListener
 *
 * @package Harmony\Bundle\AdminBundle\EventListener
 */
class MenuListener
{

    /** @var SettingsRouter $settingsRouter */
    protected $settingsRouter;

    /** @var ParameterBagInterface $parameterBag */
    protected $parameterBag;

    /** @var Filesystem $filesystem */
    protected $filesystem;

    /**
     * MenuListener constructor.
     *
     * @param SettingsRouter        $settingsRouter
     * @param ParameterBagInterface $parameterBag
     * @param Filesystem            $filesystem
     */
    public function __construct(SettingsRouter $settingsRouter, ParameterBagInterface $parameterBag,
                                Filesystem $filesystem)
    {
        $this->settingsRouter = $settingsRouter;
        $this->parameterBag   = $parameterBag;
        $this->filesystem     = $filesystem;
    }

    /**
     * @param ConfigureMenuEvent $event
     */
    public function onMenuConfigure(ConfigureMenuEvent $event)
    {
        $themeDir     = $this->parameterBag->get('kernel.theme_dir');
        $currentTheme = $this->settingsRouter->get('theme');
        if (false !== $currentTheme &&
            true === $this->filesystem->exists(sprintf('%s/%s/settings.yaml', $themeDir, $currentTheme))) {
            $menu = $event->getMenu();
            if ('admin_menu' === $menu->getName()) {
                $menu->getChild('themes')->addChild('Configure');
            }
        }
    }
}