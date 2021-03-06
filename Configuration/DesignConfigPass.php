<?php

namespace Harmony\Bundle\AdminBundle\Configuration;

use Symfony\Component\DependencyInjection\ContainerInterface;
use function in_array;
use function mb_substr;
use function preg_replace;

/**
 * Processes the custom CSS styles applied to the backend design based on the
 * value of the design configuration options.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class DesignConfigPass implements ConfigPassInterface
{

    /** @var ContainerInterface */
    private $container;

    /** @var bool */
    private $kernelDebug;

    /** @var string */
    private $locale;

    /**
     * @var ContainerInterface to prevent ServiceCircularReferenceException
     * @var bool   $debug
     * @var string $locale
     */
    public function __construct(ContainerInterface $container, $debug, $locale)
    {
        $this->container   = $container;
        $this->kernelDebug = $debug;
        $this->locale      = $locale;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function process(array $backendConfig)
    {
        $backendConfig = $this->processRtlLanguages($backendConfig);
        $backendConfig = $this->processCustomCss($backendConfig);

        return $backendConfig;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    private function processRtlLanguages(array $backendConfig)
    {
        if (!isset($backendConfig['design']['rtl'])) {
            // ar = Arabic, fa = Persian, he = Hebrew
            if (in_array(mb_substr($this->locale, 0, 2), ['ar', 'fa', 'he'])) {
                $backendConfig['design']['rtl'] = true;
            } else {
                $backendConfig['design']['rtl'] = false;
            }
        }

        return $backendConfig;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function processCustomCss(array $backendConfig)
    {
        $customCssContent = $this->container->get('twig')->render('@HarmonyAdmin/css/harmony_admin.css.twig', [
            'brand_color'  => $backendConfig['design']['brand_color'],
            'color_scheme' => $backendConfig['design']['color_scheme'],
            'kernel_debug' => $this->kernelDebug,
        ]);

        $minifiedCss                              = preg_replace(['/\n/', '/\s{2,}/'], ' ', $customCssContent);
        $backendConfig['_internal']['custom_css'] = $minifiedCss;

        return $backendConfig;
    }
}
