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

    /** @var ConfigManager */
    private $configManager;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var PropertyAccessorInterface */
    private $propertyAccessor;

    /** @var RequestStack */
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
     * @param object|string $entity
     * @param string        $action
     * @param array         $parameters
     *
     * @throws UndefinedModelException
     * @return string
     */
    public function generate($entity, $action, array $parameters = [])
    {
        if (is_object($entity)) {
            $config = $this->getEntityConfigByClass(get_class($entity));

            // casting to string is needed because entities can use objects as primary keys
            $parameters['id'] = (string)$this->propertyAccessor->getValue($entity, 'id');
        } else {
            $config = class_exists($entity) ? $this->getEntityConfigByClass($entity) :
                $this->configManager->getModelConfig($entity);
        }

        $parameters['entity'] = $config['name'];
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
    private function getEntityConfigByClass($class)
    {
        if (!$config = $this->configManager->getModelConfigByClass($this->getRealClass($class))) {
            throw new UndefinedModelException(['entity_name' => $class]);
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
