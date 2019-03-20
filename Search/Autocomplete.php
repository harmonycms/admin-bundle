<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Search;

use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * It looks for the values of model which match the given query. It's used for
 * the autocomplete field types.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class Autocomplete
{

    /** @var ConfigManager $configManager */
    private $configManager;

    /** @var Finder $finder */
    private $finder;

    /** @var PropertyAccessor $propertyAccessor */
    private $propertyAccessor;

    /**
     * Autocomplete constructor.
     *
     * @param ConfigManager    $configManager
     * @param Finder           $finder
     * @param PropertyAccessor $propertyAccessor
     */
    public function __construct(ConfigManager $configManager, Finder $finder, PropertyAccessor $propertyAccessor)
    {
        $this->configManager    = $configManager;
        $this->finder           = $finder;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * Finds the values of the given model which match the query provided.
     *
     * @param string $model
     * @param string $query
     * @param int    $page
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public function find($model, $query, $page = 1)
    {
        if (empty($model) || empty($query)) {
            return ['results' => []];
        }

        $backendConfig = $this->configManager->getBackendConfig();
        if (!isset($backendConfig['entities'][$model])) {
            throw new \InvalidArgumentException(sprintf('The "model" argument must contain the name of an model managed by HarmonyAdmin ("%s" given).',
                $model));
        }

        $paginator = $this->finder->findByAllProperties($backendConfig['models'][$model], $query, $page,
            $backendConfig['show']['max_results']);

        return [
            'results'       => $this->processResults($paginator->getCurrentPageResults(),
                $backendConfig['models'][$model]),
            'has_next_page' => $paginator->hasNextPage(),
        ];
    }

    /**
     * @param       $models
     * @param array $targetModelConfig
     *
     * @return array
     */
    private function processResults($models, array $targetModelConfig)
    {
        $results = [];
        foreach ($models as $model) {
            $results[] = [
                'id'   => $this->propertyAccessor->getValue($model, $targetModelConfig['primary_key_field_name']),
                'text' => (string)$model,
            ];
        }

        return $results;
    }
}
