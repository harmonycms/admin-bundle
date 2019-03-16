<?php

namespace Harmony\Bundle\AdminBundle\EventListener;

use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\MenuBundle\Event\ConfigureMenuEvent;
use Harmony\Bundle\MenuBundle\Menu\MenuDomain;
use Harmony\Sdk\Theme\ThemeInterface;
use Harmony\Bundle\SettingsManagerBundle\Settings\SettingsRouter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class MenuListener
 *
 * @package Harmony\Bundle\AdminBundle\EventListener
 */
class MenuListener
{

    /** @var SettingsRouter $settingsRouter */
    protected $settingsRouter;

    /** @var Filesystem $filesystem */
    protected $filesystem;

    /** @var KernelInterface $kernel */
    protected $kernel;

    /**
     * MenuListener constructor.
     *
     * @param SettingsRouter                 $settingsRouter
     * @param KernelInterface|AbstractKernel $kernel
     * @param Filesystem                     $filesystem
     */
    public function __construct(SettingsRouter $settingsRouter, KernelInterface $kernel, Filesystem $filesystem)
    {
        $this->settingsRouter = $settingsRouter;
        $this->filesystem     = $filesystem;
        $this->kernel         = $kernel;
    }

    /**
     * @param ConfigureMenuEvent $event
     */
    public function onMenuConfigure(ConfigureMenuEvent $event)
    {
        $menu = $event->getMenu();
        if ('admin_menu' === $menu->getName()) {
            $menu->setDomain(new MenuDomain('admin'));
        }

        $themes       = $this->kernel->getThemes();
        $currentTheme = $this->settingsRouter->get('theme');
        if (isset($themes[$currentTheme])) {
            /** @var ThemeInterface $theme */
            $theme = $themes[$currentTheme];
            if (true === $theme->hasSettings()) {
                if ('admin_menu' === $menu->getName()) {
                    $menu->getChild('themes')->addChild('Configure', [
                        'route'           => 'admin_settings_index',
                        'routeParameters' => ['domainName' => 'theme', 'tagName' => $currentTheme]
                    ]);
                }
            }
        }
    }
}