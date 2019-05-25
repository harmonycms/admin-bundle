<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Exception;
use Harmony\Bundle\CoreBundle\Component\HttpKernel\AbstractKernel;
use Harmony\Bundle\CoreBundle\Model\Config;
use Harmony\Bundle\CoreBundle\Model\ConfigInterface;
use Harmony\Bundle\CoreBundle\Model\Extension;
use Harmony\Bundle\CoreBundle\Model\ExtensionInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
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

    /** @var AbstractKernel|KernelInterface $kernel */
    protected $kernel;

    /** @var TranslatorInterface $translator */
    protected $translator;

    /** @var string|null $defaultTheme */
    protected $defaultTheme;

    /** @var ManagerRegistry $registry */
    protected $registry;

    /**
     * ThemeController constructor.
     *
     * @param KernelInterface|AbstractKernel $kernel
     * @param TranslatorInterface            $translator
     * @param ManagerRegistry                $registry
     * @param string|null                    $defaultTheme
     */
    public function __construct(KernelInterface $kernel, TranslatorInterface $translator, ManagerRegistry $registry,
                                string $defaultTheme = null)
    {
        $this->kernel       = $kernel;
        $this->translator   = $translator;
        $this->registry     = $registry;
        $this->defaultTheme = $defaultTheme;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('@HarmonyAdmin\theme\index.html.twig', [
            'themes'        => $this->kernel->getThemes(),
            'default_theme' => $this->defaultTheme
        ]);
    }

    /**
     * @Route("/activate/{name}", name="activate")
     * @param string $name
     *
     * @return Response
     * @throws Exception
     */
    public function activate(string $name): Response
    {
        $manager             = $this->registry->getManager();
        $configRepository    = $this->registry->getRepository(ConfigInterface::class);
        $extensionRepository = $this->registry->getRepository(ExtensionInterface::class);

        if (null === $extension = $extensionRepository->findOneBy(['name' => 'harmony'])) {
            $extensionClass = $manager->getClassMetadata(ExtensionInterface::class)->getName();
            $extension      = new $extensionClass();
            $extension->setName('harmony');
            $manager->persist($extension);
            $manager->flush();
        }

        if (null === $config = $configRepository->findOneBy(['name' => 'theme_default', 'extension' => $extension])) {
            $configClass = $manager->getClassMetadata(ConfigInterface::class)->getName();
            $config      = new $configClass();
            $config->setName('theme_default')->setExtension($extension);
            $manager->persist($config);
        }
        $config->setValue($name);
        $manager->flush();

        $this->doCommand($this->kernel, 'cache:clear');

        $this->addFlash('success',
            $this->translator->trans('theme.activated_success', ['%name%' => $name], 'HarmonyAdminBundle'));

        return $this->redirectToRoute('admin_theme_index');
    }

    /**
     * @Route("/deactivate/{name}", name="deactivate")
     * @param string $name
     *
     * @return Response
     * @throws Exception
     */
    public function deactivate(string $name): Response
    {
        $extension = $this->registry->getRepository(ExtensionInterface::class)->findOneBy(['name' => 'harmony']);
        $config    = $this->registry->getRepository(ConfigInterface::class)->findOneBy([
            'name'      => 'theme_default',
            'extension' => $extension
        ]);
        $this->registry->getManager()->remove($config);
        $this->registry->getManager()->flush();

        $this->doCommand($this->kernel, 'cache:clear');

        $this->addFlash('success',
            $this->translator->trans('theme.deactivated_success', ['%name%' => $name], 'HarmonyAdminBundle'));

        return $this->redirectToRoute('admin_theme_index');
    }

    /**
     * @param KernelInterface $kernel
     * @param string          $command
     *
     * @return Response
     * @throws Exception
     */
    protected function doCommand(KernelInterface $kernel, string $command): Response
    {
        $env = $kernel->getEnvironment();

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(['command' => $command, '--env' => $env]);

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        // return the output, don't use if you used NullOutput()
        $content = $output->fetch();

        // return new Response(""), if you used NullOutput()
        return new Response($content);
    }
}