<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Router;

use Doctrine\Common\Persistence\Proxy;
use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Harmony\Bundle\AdminBundle\Exception\UndefinedModelException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use function class_exists;
use function get_class;
use function in_array;
use function is_object;
use function is_string;
use function strrpos;
use function substr;
use function urlencode;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class HarmonyAdminRouter
{

    /** @var ConfigManager $configManager */
    private $configManager;

    /** @var UrlGeneratorInterface $urlGenerator */
    private $urlGenerator;

    /** @var PropertyAccessorInterface $propertyAccessor */
    private $propertyAccessor;

    /** @var RequestStack $requestStack */
    private $requestStack;

    /**
     * HarmonyAdminRouter constructor.
     *
     * @param ConfigManager             $configManager
     * @param UrlGeneratorInterface     $urlGenerator
     * @param PropertyAccessorInterface $propertyAccessor
     * @param RequestStack|null         $requestStack
     */
    public function __construct(ConfigManager $configManager, UrlGeneratorInterface $urlGenerator,
                                PropertyAccessorInterface $propertyAccessor, RequestStack $requestStack = null)
    {
        $this->configManager    = $configManager;
        $this->urlGenerator     = $urlGenerator;
        $this->propertyAccessor = $propertyAccessor;
        $this->requestStack     = $requestStack;
    }

    /**
     * @param object|string $model
     * @param string        $action
     * @param array         $parameters
     *
     * @throws UndefinedModelException
     * @return string
     */
    public function generate($model, $action, array $parameters = [])
    {
        if (is_object($model)) {
            $config = $this->getModelConfigByClass(get_class($model));

            // casting to string is needed because entities can use objects as primary keys
            $parameters['id'] = (string)$this->propertyAccessor->getValue($model, 'id');
        } else {
            $config = class_exists($model) ? $this->getModelConfigByClass($model) :
                $this->configManager->getModelConfig($model);
        }

        $parameters['model']  = $config['name'];
        $parameters['action'] = $action;

        $referer = $parameters['referer'] ?? null;

        $request = null;
        if (null !== $this->requestStack) {
            $request = $this->requestStack->getCurrentRequest();
        }

        if (false === $referer) {
            unset($parameters['referer']);
        } elseif ($request && !is_string($referer) &&
            (true === $referer || in_array($action, ['new', 'edit', 'delete'], true))) {
            $parameters['referer'] = urlencode($request->getUri());
        }

        return $this->urlGenerator->generate('admin', $parameters);
    }

    /**
     * @param string $class
     *
     * @throws UndefinedModelException
     * @return array
     */
    private function getModelConfigByClass($class)
    {
        if (!$config = $this->configManager->getModelConfigByClass($this->getRealClass($class))) {
            throw new UndefinedModelException(['model_name' => $class]);
        }

        return $config;
    }

    /**
     * @param string $class
     *
     * @return string
     */
    private function getRealClass($class)
    {
        if (false === $pos = strrpos($class, '\\' . Proxy::MARKER . '\\')) {
            return $class;
        }

        return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
    }
}
