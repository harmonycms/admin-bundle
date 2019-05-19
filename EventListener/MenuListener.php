<?php

namespace Harmony\Bundle\AdminBundle\EventListener;

use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\MenuBundle\Event\ConfigureMenuEvent;
use Harmony\Bundle\MenuBundle\Menu\MenuDomain;
use Harmony\Sdk\Theme\ThemeInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class MenuListener
 *
 * @package Harmony\Bundle\AdminBundle\EventListener
 */
class MenuListener
{

    /** @var Filesystem $filesystem */
    protected $filesystem;

    /** @var KernelInterface $kernel */
    protected $kernel;

    /** @var string|null $defaultTheme */
    protected $defaultTheme;

    /**
     * MenuListener constructor.
     *
     * @param KernelInterface|AbstractKernel $kernel
     * @param Filesystem                     $filesystem
     * @param string|null                    $defaultTheme
     */
    public function __construct(KernelInterface $kernel, Filesystem $filesystem, string $defaultTheme = null)
    {
        $this->filesystem   = $filesystem;
        $this->kernel       = $kernel;
        $this->defaultTheme = $defaultTheme;
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

        $themes = $this->kernel->getThemes();
        if (isset($themes[$this->defaultTheme])) {
            /** @var ThemeInterface $theme */
            $theme = $themes[$this->defaultTheme];
            if (true === $theme->hasSettings()) {
                if ('admin_menu' === $menu->getName()) {
//                    $menu->getChild('themes')
//                        ->addChild('Configure', [
//                            'route'           => 'admin_settings_index',
//                            'routeParameters' => ['domainName' => 'theme', 'tagName' => $this->defaultTheme]
//                        ])
//                    ;
                }
            }
        }
    }
}