<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Configuration;

use Harmony\Bundle\AdminBundle\Exception\UndefinedModelException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Class ConfigManager
 *
 * @package Harmony\Bundle\AdminBundle\Configuration
 */
final class ConfigManager
{

    private const CACHE_KEY = 'harmony_admin.processed_config';

    /** @var array $backendConfig */
    private $backendConfig;

    /** @var bool $debug */
    private $debug;

    /** @var PropertyAccessorInterface $propertyAccessor */
    private $propertyAccessor;

    /** @var CacheItemPoolInterface $cache */
    private $cache;

    /** @var array $originalBackendConfig */
    private $originalBackendConfig;

    /** @var ConfigPassInterface[] $configPasses */
    private $configPasses;

    /**
     * ConfigManager constructor.
     *
     * @param array                     $originalBackendConfig
     * @param bool                      $debug
     * @param PropertyAccessorInterface $propertyAccessor
     * @param CacheItemPoolInterface    $cache
     */
    public function __construct(array $originalBackendConfig, bool $debug, PropertyAccessorInterface $propertyAccessor,
                                CacheItemPoolInterface $cache)
    {
        $this->originalBackendConfig = $originalBackendConfig;
        $this->debug                 = $debug;
        $this->propertyAccessor      = $propertyAccessor;
        $this->cache                 = $cache;
    }

    /**
     * @param ConfigPassInterface $configPass
     */
    public function addConfigPass(ConfigPassInterface $configPass)
    {
        $this->configPasses[] = $configPass;
    }

    /**
     * @param string|null $propertyPath
     *
     * @return array|mixed
     */
    public function getBackendConfig(string $propertyPath = null)
    {
        $this->backendConfig = $this->loadBackendConfig();

        if (empty($propertyPath)) {
            return $this->backendConfig;
        }

        // turns 'design.menu' into '[design][menu]', the format required by PropertyAccess
        $propertyPath = '[' . str_replace('.', '][', $propertyPath) . ']';

        return $this->propertyAccessor->getValue($this->backendConfig, $propertyPath);
    }

    /**
     * @param string $modelName
     *
     * @return array
     */
    public function getModelConfig(string $modelName): array
    {
        $backendConfig = $this->getBackendConfig();
        if (!isset($backendConfig['models'][$modelName])) {
            throw new UndefinedModelException(['model_name' => $modelName]);
        }

        return $backendConfig['models'][$modelName];
    }

    /**
     * @param string $fqcn
     *
     * @return array|null
     */
    public function getModelConfigByClass(string $fqcn): ?array
    {
        $backendConfig = $this->getBackendConfig();
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            if ($modelConfig['class'] === $fqcn) {
                return $modelConfig;
            }
        }

        return null;
    }

    /**
     * @param string $modelName
     * @param string $view
     * @param string $action
     *
     * @return array
     */
    public function getActionConfig(string $modelName, string $view, string $action): array
    {
        try {
            $modelConfig = $this->getModelConfig($modelName);
        }
        catch (\Exception $e) {
            $modelConfig = [];
        }

        return $modelConfig[$view]['actions'][$action] ?? [];
    }

    /**
     * @param string $modelName
     * @param string $view
     * @param string $action
     *
     * @return bool
     */
    public function isActionEnabled(string $modelName, string $view, string $action): bool
    {
        $modelConfig = $this->getModelConfig($modelName);

        return !\in_array($action, $modelConfig['disabled_actions'], true) &&
            array_key_exists($action, $modelConfig[$view]['actions']);
    }

    /**
     * It processes the given backend configuration to generate the fully
     * processed configuration used in the application.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function doProcessConfig($backendConfig): array
    {
        foreach ($this->configPasses as $configPass) {
            $backendConfig = $configPass->process($backendConfig);
        }

        return $backendConfig;
    }

    /**
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function loadBackendConfig(): array
    {
        if (true === $this->debug) {
            return $this->doProcessConfig($this->originalBackendConfig);
        }

        $cachedBackendConfig = $this->cache->getItem(self::CACHE_KEY);

        if ($cachedBackendConfig->isHit()) {
            return $cachedBackendConfig->get();
        }

        $backendConfig = $this->doProcessConfig($this->originalBackendConfig);
        $cachedBackendConfig->set($backendConfig);
        $this->cache->save($cachedBackendConfig);

        return $backendConfig;
    }
}
