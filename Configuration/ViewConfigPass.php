<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Configuration;

use function array_key_exists;
use function array_slice;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function mb_substr;
use function strpos;
use function strtoupper;
use function substr_count;

/**
 * Initializes the configuration for all the views of each model, which is
 * needed when some model relies on the default configuration for some view.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ViewConfigPass implements ConfigPassInterface
{

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig)
    {
        $backendConfig = $this->processViewConfig($backendConfig);
        $backendConfig = $this->processDefaultFieldsConfig($backendConfig);
        $backendConfig = $this->processFieldConfig($backendConfig);
        $backendConfig = $this->processPageTitleConfig($backendConfig);
        $backendConfig = $this->processMaxResultsConfig($backendConfig);
        $backendConfig = $this->processSortingConfig($backendConfig);

        return $backendConfig;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    private function processViewConfig(array $backendConfig)
    {
        // process the 'help' message that each view can define to display it under the page title
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['edit', 'list', 'new', 'search', 'show'] as $view) {
                // isset() cannot be used because the value can be 'null' (used to remove the inherited help message)
                if (array_key_exists('help', $backendConfig['models'][$modelName][$view])) {
                    continue;
                }

                $backendConfig['models'][$modelName][$view]['help'] = array_key_exists('help', $modelConfig) ?
                    $modelConfig['help'] : null;
            }
        }

        return $backendConfig;
    }

    /**
     * This method takes care of the views that don't define their fields. In
     * those cases, we just use the $modelConfig['properties'] information and
     * we filter some fields to improve the user experience for default config.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processDefaultFieldsConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['edit', 'list', 'new', 'search', 'show'] as $view) {
                if (0 === count($modelConfig[$view]['fields'])) {
                    $fieldsConfig = $this->filterFieldList($modelConfig['properties'],
                        $this->getExcludedFieldNames($view, $modelConfig), $this->getExcludedFieldTypes($view),
                        $this->getMaxNumberFields($view));

                    foreach ($fieldsConfig as $fieldName => $fieldConfig) {
                        if (null === $fieldsConfig[$fieldName]['format']) {
                            $fieldsConfig[$fieldName]['format'] = $this->getFieldFormat($fieldConfig['type'],
                                $backendConfig);
                        }
                    }

                    $backendConfig['models'][$modelName][$view]['fields'] = $fieldsConfig;
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This methods makes some minor tweaks in fields configuration to improve
     * the user experience.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processFieldConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['edit', 'list', 'new', 'search', 'show'] as $view) {
                foreach ($modelConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    // special case: if the field is called 'id' and doesn't define a custom
                    // label, use 'ID' as label. This improves the readability of the label
                    // of this important field, which is usually related to the primary key
                    if ('id' === $fieldConfig['fieldName'] && !isset($fieldConfig['label'])) {
                        $fieldConfig['label'] = 'ID';
                    }

                    $backendConfig['models'][$modelName][$view]['fields'][$fieldName] = $fieldConfig;
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This method resolves the page title inheritance when some global view
     * (list, edit, etc.) defines a global title for all models that can be
     * overridden individually by each model.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processPageTitleConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['edit', 'list', 'new', 'search', 'show'] as $view) {
                if (!isset($modelConfig[$view]['title']) && isset($backendConfig[$view]['title'])) {
                    $backendConfig['models'][$modelName][$view]['title'] = $backendConfig[$view]['title'];
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This method resolves the 'max_results' inheritance when some global view
     * (list, show, etc.) defines a global value for all models that can be
     * overridden individually by each model.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processMaxResultsConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['list', 'search', 'show'] as $view) {
                if (!isset($modelConfig[$view]['max_results']) && isset($backendConfig[$view]['max_results'])) {
                    $backendConfig['models'][$modelName][$view]['max_results'] = $backendConfig[$view]['max_results'];
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This method processes the optional 'sort' config that the 'list' and
     * 'search' views can define to override the default (id, DESC) sorting
     * applied to their contents.
     *
     * @param array $backendConfig
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    private function processSortingConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['list', 'search'] as $view) {
                if (!isset($modelConfig[$view]['sort'])) {
                    continue;
                }

                $sortConfig = $modelConfig[$view]['sort'];
                if (!is_string($sortConfig) && !is_array($sortConfig)) {
                    throw new \InvalidArgumentException(sprintf('The "sort" option of the "%s" view of the "%s" model contains an invalid value (it can only be a string or an array).',
                        $view, $modelName));
                }

                if (is_string($sortConfig)) {
                    $sortConfig = ['field' => $sortConfig, 'direction' => 'DESC'];
                } else {
                    $sortConfig = ['field' => $sortConfig[0], 'direction' => strtoupper($sortConfig[1])];
                }

                if (!in_array($sortConfig['direction'], ['ASC', 'DESC'])) {
                    throw new \InvalidArgumentException(sprintf('If defined, the second value of the "sort" option of the "%s" view of the "%s" model can only be "ASC" or "DESC".',
                        $view, $modelName));
                }

                $isSortedByDoctrineAssociation = false !== strpos($sortConfig['field'], '.');
                if (!$isSortedByDoctrineAssociation && (isset($modelConfig[$view]['fields'][$sortConfig['field']]) &&
                        true === $modelConfig[$view]['fields'][$sortConfig['field']]['virtual'])) {
                    throw new \InvalidArgumentException(sprintf('The "%s" field cannot be used in the "sort" option of the "%s" view of the "%s" model because it\'s a virtual property that is not persisted in the database.',
                        $sortConfig['field'], $view, $modelName));
                }

                // sort can be defined using simple properties (sort: author) or association properties (sort: author.name)
                if (substr_count($sortConfig['field'], '.') > 1) {
                    throw new \InvalidArgumentException(sprintf('The "%s" value cannot be used as the "sort" option in the "%s" view of the "%s" model because it defines multiple sorting levels (e.g. "aaa.bbb.ccc") but only up to one level is supported (e.g. "aaa.bbb").',
                        $sortConfig['field'], $view, $modelName));
                }

                // sort field can be a Doctrine association (sort: author.name) instead of a simple property
                $sortFieldParts    = explode('.', $sortConfig['field']);
                $sortFieldProperty = $sortFieldParts[0];

                if (!array_key_exists($sortFieldProperty, $modelConfig['properties']) &&
                    !isset($modelConfig[$view]['fields'][$sortFieldProperty])) {
                    throw new \InvalidArgumentException(sprintf('The "%s" field used in the "sort" option of the "%s" view of the "%s" model does not exist neither as a property of that model nor as a virtual field of that view.',
                        $sortFieldProperty, $view, $modelName));
                }

                $backendConfig['models'][$modelName][$view]['sort'] = $sortConfig;
            }
        }

        return $backendConfig;
    }

    /**
     * Returns the date/time/datetime/number format for the given field
     * according to its type and the default formats defined for the backend.
     *
     * @param string $fieldType
     * @param array  $backendConfig
     *
     * @return string The format that should be applied to the field value
     */
    private function getFieldFormat($fieldType, array $backendConfig)
    {
        if (in_array($fieldType, [
            'date',
            'date_immutable',
            'dateinterval',
            'time',
            'time_immutable',
            'datetime',
            'datetime_immutable',
            'datetimetz'
        ])) {
            // make 'datetimetz' use the same format as 'datetime'
            $fieldType = ('datetimetz' === $fieldType) ? 'datetime' : $fieldType;
            $fieldType = ('_immutable' === mb_substr($fieldType, - 10)) ? mb_substr($fieldType, 0, - 10) : $fieldType;

            return $backendConfig['formats'][$fieldType];
        }

        if (in_array($fieldType, ['bigint', 'integer', 'smallint', 'decimal', 'float'])) {
            return isset($backendConfig['formats']['number']) ? $backendConfig['formats']['number'] : null;
        }
    }

    /**
     * Returns the list of excluded field names for the given view.
     *
     * @param string $view
     * @param array  $modelConfig
     *
     * @return array
     */
    private function getExcludedFieldNames($view, array $modelConfig)
    {
        $excludedFieldNames = [
            'edit'   => [$modelConfig['primary_key_field_name']],
            'list'   => ['password', 'salt', 'slug', 'updatedAt', 'uuid'],
            'new'    => [$modelConfig['primary_key_field_name']],
            'search' => ['password', 'salt'],
            'show'   => [],
        ];

        return isset($excludedFieldNames[$view]) ? $excludedFieldNames[$view] : [];
    }

    /**
     * Returns the list of excluded field types for the given view.
     *
     * @param string $view
     *
     * @return array
     */
    private function getExcludedFieldTypes($view)
    {
        $excludedFieldTypes = [
            'edit'   => ['binary', 'blob', 'json_array', 'json', 'object'],
            'list'   => ['array', 'binary', 'blob', 'guid', 'json_array', 'json', 'object', 'simple_array', 'text'],
            'new'    => ['binary', 'blob', 'json_array', 'json', 'object'],
            'search' => [
                'association',
                'binary',
                'boolean',
                'blob',
                'date',
                'date_immutable',
                'datetime',
                'datetime_immutable',
                'dateinterval',
                'datetimetz',
                'time',
                'time_immutable',
                'object'
            ],
            'show'   => [],
        ];

        return isset($excludedFieldTypes[$view]) ? $excludedFieldTypes[$view] : [];
    }

    /**
     * Returns the maximum number of fields to display be default for the
     * given view.
     *
     * @param string $view
     *
     * @return int
     */
    private function getMaxNumberFields($view)
    {
        $maxNumberFields = [
            'list' => 7,
        ];

        return isset($maxNumberFields[$view]) ? $maxNumberFields[$view] : PHP_INT_MAX;
    }

    /**
     * Filters a list of fields excluding the given list of field names and field types.
     *
     * @param array    $fields
     * @param string[] $excludedFieldNames
     * @param string[] $excludedFieldTypes
     * @param int      $maxNumFields
     *
     * @return array The filtered list of fields
     */
    private function filterFieldList(array $fields, array $excludedFieldNames, array $excludedFieldTypes, $maxNumFields)
    {
        $filteredFields = [];

        foreach ($fields as $name => $metadata) {
            if (!in_array($name, $excludedFieldNames) && !in_array($metadata['type'], $excludedFieldTypes)) {
                $filteredFields[$name] = $fields[$name];
            }
        }

        if (count($filteredFields) > $maxNumFields) {
            $filteredFields = array_slice($filteredFields, 0, $maxNumFields, true);
        }

        return $filteredFields;
    }
}
