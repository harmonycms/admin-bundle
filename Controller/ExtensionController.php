<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Sdk\Extension\AbstractExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ExtensionController
 * @Route("/extension", name="admin_extension_")
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class ExtensionController extends AbstractController
{

    /** @var KernelInterface|AbstractKernel $kernel */
    protected $kernel;

    /**
     * ExtensionController constructor.
     *
     * @param KernelInterface|AbstractKernel $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Route("/modules", name="modules")
     * @return Response
     */
    public function modules(): Response
    {
        $modules = [];
        foreach ($this->kernel->getExtensions() as $name => $extension) {
            if (AbstractExtension::MODULE === $extension->getExtensionType()) {
                $modules[$name] = $extension;
            }
        }

        return $this->render('@HarmonyAdmin\extension\modules.html.twig', ['modules' => $modules]);
    }
}