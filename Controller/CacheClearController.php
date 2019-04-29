<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CacheClearController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class CacheClearController extends AbstractController
{

    /**
     * @Route("/cache/clear", name="cache_clear")
     * @param Request         $request
     * @param KernelInterface $kernel
     *
     * @return RedirectResponse
     * @throws Exception
     */
    public function cacheClear(Request $request, KernelInterface $kernel): RedirectResponse
    {
        $this->doCommand($kernel, 'cache:clear');

        $this->addFlash('success', sprintf('Cache for the "%s" environment was successfully cleared.',
            $kernel->getEnvironment()));

        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * @Route("/cache/warmup", name="cache_warmup")
     * @param Request         $request
     * @param KernelInterface $kernel
     *
     * @return RedirectResponse
     * @throws Exception
     */
    public function cacheWarmup(Request $request, KernelInterface $kernel): RedirectResponse
    {
        $this->doCommand($kernel, 'cache:warmup');

        $this->addFlash('success', sprintf('Cache for the "%s" environment was successfully warmed.',
            $kernel->getEnvironment()));

        return $this->redirect($request->headers->get('referer'));
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