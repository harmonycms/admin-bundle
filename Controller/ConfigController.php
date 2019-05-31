<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Harmony\Bundle\CoreBundle\Model\ConfigInterface;
use ReflectionException;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use function array_flip;
use function ksort;
use function sprintf;

/**
 * Class ConfigController
 * @Route("/settings/config", name="admin_settings_config_")
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class ConfigController extends AbstractController
{

    /** @var ManagerRegistry $registry */
    protected $registry;

    /** @var KernelInterface $kernel */
    protected $kernel;

    /** @var ContainerBuilder $containerBuilder */
    protected $containerBuilder;

    protected $extensionConfig        = [];

    protected $bundleExtensionMapping = [];

    /**
     * ConfigController constructor.
     *
     * @param ManagerRegistry $registry
     * @param KernelInterface $kernel
     *
     * @throws ReflectionException
     */
    public function __construct(ManagerRegistry $registry, KernelInterface $kernel)
    {
        $this->registry         = $registry;
        $this->kernel           = $kernel;
        $this->containerBuilder = $this->compileContainer();

        foreach ($this->containerBuilder->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof ValidateEnvPlaceholdersPass) {
                $this->extensionConfig = $pass->getExtensionConfig();
                break;
            }
        }

        $this->bundleExtensionMapping = $this->listBundles();
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('@HarmonyAdmin\settings\config.html.twig', [
            'extensions' => $this->bundleExtensionMapping,
            'config'     => $this->registry->getRepository(ConfigInterface::class)->findAll()
        ]);
    }

    /**
     * @Route("/add/{alias}", name="add")
     * @param string $alias
     *
     * @return Response
     */
    public function addExtensionParameters(string $alias): Response
    {
        if ($bundleName = array_flip($this->bundleExtensionMapping)[$alias]) {
            $bundle = $this->kernel->getBundle($bundleName);
        }

        if (!isset($this->extensionConfig[$alias])) {
            throw new \LogicException(sprintf('The extension with alias "%s" does not have configuration.', $alias));
        }

        dd($this->extensionConfig[$alias]);
    }

    /**
     * List bundles with extension alias.
     *
     * @return array
     */
    protected function listBundles(): array
    {
        $extensions = [];
        foreach ($this->kernel->getBundles() as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                if (isset($this->extensionConfig[$extension->getAlias()])) {
                    $extensions[$bundle->getName()] = $extension->getAlias();
                }
            }
        }
        ksort($extensions);

        return $extensions;
    }

    /**
     * @return ContainerBuilder
     * @throws ReflectionException
     */
    private function compileContainer(): ContainerBuilder
    {
        $kernel = clone $this->kernel;
        $kernel->boot();

        $method = new ReflectionMethod($kernel, 'buildContainer');
        $method->setAccessible(true);
        $container = $method->invoke($kernel);
        $container->getCompiler()->compile($container);

        return $container;
    }
}