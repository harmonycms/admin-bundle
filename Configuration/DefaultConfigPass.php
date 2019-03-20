<?php

namespace Harmony\Bundle\AdminBundle\Configuration;

use function array_keys;

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
        return $this->processDefaultModel($backendConfig);
    }

    /**
     * Finds the default model to display when the backend index is not
     * defined explicitly.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processDefaultModel(array $backendConfig)
    {
        $modelNames                          = array_keys($backendConfig['models']);
        $firstModelName                      = isset($modelNames[0]) ? $modelNames[0] : null;
        $backendConfig['default_model_name'] = $firstModelName;

        return $backendConfig;
    }
}
