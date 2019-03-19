<?php

namespace Harmony\Bundle\AdminBundle\Configuration;

/**
 * Processes default values for some backend configuration options.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class DefaultConfigPass implements ConfigPassInterface
{

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig)
    {
        return $this->processDefaultEntity($backendConfig);
    }

    /**
     * Finds the default entity to display when the backend index is not
     * defined explicitly.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processDefaultEntity(array $backendConfig)
    {
        $entityNames                          = array_keys($backendConfig['models']);
        $firstEntityName                      = isset($entityNames[0]) ? $entityNames[0] : null;
        $backendConfig['default_entity_name'] = $firstEntityName;

        return $backendConfig;
    }
}
