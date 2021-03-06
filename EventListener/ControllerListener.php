<?php

namespace Harmony\Bundle\AdminBundle\EventListener;

use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use function class_exists;
use function is_array;
use function sprintf;

/**
 * Sets the right controller to be executed when entities define custom
 * controllers.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ControllerListener
{

    /** @var ConfigManager */
    private $configManager;

    /** @var ControllerResolverInterface */
    private $resolver;

    /**
     * ControllerListener constructor.
     *
     * @param ConfigManager               $configManager
     * @param ControllerResolverInterface $resolver
     */
    public function __construct(ConfigManager $configManager, ControllerResolverInterface $resolver)
    {
        $this->configManager = $configManager;
        $this->resolver      = $resolver;
    }

    /**
     * Exchange default admin controller by custom model admin controller.
     *
     * @param FilterControllerEvent $event
     *
     * @throws NotFoundHttpException
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('admin' !== $request->attributes->get('_route')) {
            return;
        }

        $currentController = $event->getController();
        // if the controller is defined in a class, $currentController is an array
        // otherwise do nothing because it's a Closure (rare but possible in Symfony)
        if (!is_array($currentController)) {
            return;
        }

        // this condition happens when accessing the backend homepage, which
        // then redirects to the 'list' action of the first configured model.
        if (null === $modelName = $request->query->get('model')) {
            return;
        }

        $model = $this->configManager->getModelConfig($modelName);

        // if the model doesn't define a custom controller, do nothing
        if (!isset($model['controller'])) {
            return;
        }

        $customController = $model['controller'];
        $controllerMethod = $currentController[1];

        // build the full controller name depending on its type
        if (class_exists($customController) || Kernel::VERSION_ID >= 40100) {
            // 'class::method' syntax for normal controllers
            $customController .= '::' . $controllerMethod;
        } else {
            // 'service:method' syntax for controllers as services
            $customController .= ':' . $controllerMethod;
        }

        $request->attributes->set('_controller', $customController);
        $newController = $this->resolver->getController($request);

        if (false === $newController) {
            throw new NotFoundHttpException(sprintf('Unable to find the controller for path "%s". Check the "controller" configuration of the "%s" model in your HarmonyAdmin backend.',
                $request->getPathInfo(), $modelName));
        }

        $event->setController($newController);
    }
}
