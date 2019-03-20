<?php

namespace Harmony\Bundle\AdminBundle\EventListener;

use Harmony\Bundle\AdminBundle\Exception\BaseException;
use Harmony\Bundle\AdminBundle\Exception\FlattenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener as BaseExceptionListener;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * This listener allows to display customized error pages in the production
 * environment.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ExceptionListener extends BaseExceptionListener
{

    /** @var Environment $twig */
    private $twig;

    /** @var array $harmonyAdminConfig */
    private $harmonyAdminConfig;

    /** @var string $currentModelName */
    private $currentModelName;

    /**
     * ExceptionListener constructor.
     *
     * @param Environment          $twig
     * @param array                $harmonyAdminConfig
     * @param                      $controller
     * @param LoggerInterface|null $logger
     */
    public function __construct(Environment $twig, array $harmonyAdminConfig, $controller,
                                LoggerInterface $logger = null)
    {
        $this->twig               = $twig;
        $this->harmonyAdminConfig = $harmonyAdminConfig;

        parent::__construct($controller, $logger);
    }

    /**
     * @param GetResponseForExceptionEvent $event
     *
     * @throws \Exception
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception              = $event->getException();
        $this->currentModelName = $event->getRequest()->query->get('model');

        if (!$exception instanceof BaseException) {
            return;
        }

        parent::onKernelException($event);
    }

    /**
     * @param FlattenException $exception
     *
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function showExceptionPageAction(FlattenException $exception)
    {
        $modelConfig           = $this->harmonyAdminConfig['entities'][$this->currentModelName] ?? null;
        $exceptionTemplatePath = $modelConfig['templates']['exception'] ??
            $this->harmonyAdminConfig['design']['templates']['exception'] ??
            '@HarmonyAdmin/default/exception.html.twig';

        return Response::create($this->twig->render($exceptionTemplatePath, ['exception' => $exception]),
            $exception->getStatusCode());
    }

    /**
     * {@inheritdoc}
     */
    protected function logException(\Exception $exception, $message, $original = true)
    {
        if (!$exception instanceof BaseException) {
            parent::logException($exception, $message);

            return;
        }

        if (null !== $this->logger) {
            if ($exception->getStatusCode() >= 500) {
                $this->logger->critical($message, ['exception' => $exception]);
            } else {
                $this->logger->error($message, ['exception' => $exception]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function duplicateRequest(\Exception $exception, Request $request)
    {
        $request = parent::duplicateRequest($exception, $request);

        $request->attributes->set('exception', FlattenException::create($exception));

        return $request;
    }
}
