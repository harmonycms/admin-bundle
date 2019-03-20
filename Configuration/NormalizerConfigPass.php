<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Configuration;

use Symfony\Component\DependencyInjection\ContainerInterface;
use function array_key_exists;
use function array_merge;
use function array_replace_recursive;
use function array_slice;
use function class_exists;
use function in_array;
use function is_array;
use function is_string;
use function mb_substr;
use function sprintf;
use function strpos;
use function trim;

/**
 * Normalizes the different configuration formats available for models, views,
 * actions and properties.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class NormalizerConfigPass implements ConfigPassInterface
{

    /** @var array $defaultViewConfig */
    private $defaultViewConfig
        = [
            'list'   => [
                'dql_filter' => null,
                'fields'     => [],
            ],
            'search' => [
                'dql_filter' => null,
                'fields'     => [],
            ],
            'show'   => [
                'fields' => [],
            ],
            'form'   => [
                'fields'       => [],
                'form_options' => [],
            ],
            'edit'   => [
                'fields'       => [],
                'form_options' => [],
            ],
            'new'    => [
                'fields'       => [],
                'form_options' => [],
            ],
        ];

    /** @var ContainerInterface */
    private $container;

    /**
     * NormalizerConfigPass constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig)
    {
        $backendConfig = $this->normalizeModelConfig($backendConfig);
        $backendConfig = $this->normalizeViewConfig($backendConfig);
        $backendConfig = $this->normalizePropertyConfig($backendConfig);
        $backendConfig = $this->normalizeFormDesignConfig($backendConfig);
        $backendConfig = $this->normalizeActionConfig($backendConfig);
        $backendConfig = $this->normalizeFormConfig($backendConfig);
        $backendConfig = $this->normalizeControllerConfig($backendConfig);
        $backendConfig = $this->normalizeTranslationConfig($backendConfig);

        return $backendConfig;
    }

    /**
     * By default the model name is used as its label (showed in buttons, the
     * main menu, etc.) unless the model config defines the 'label' option:.
     * harmony_admin:
     *     models:
     *         User:
     *             class: AppBundle\Entity\User
     *             label: 'Clients'
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeModelConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            if (!isset($modelConfig['label'])) {
                $backendConfig['models'][$modelName]['label'] = $modelName;
            }
        }

        return $backendConfig;
    }

    /**
     * Process the configuration of the 'form' view (if any) to complete the
     * configuration of the 'edit' and 'new' views.
     *
     * @param array $backendConfig [description]
     *
     * @return array
     */
    private function normalizeFormConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            if (isset($modelConfig['form'])) {
                $modelConfig['new']  = isset($modelConfig['new']) ?
                    $this->mergeFormConfig($modelConfig['form'], $modelConfig['new']) : $modelConfig['form'];
                $modelConfig['edit'] = isset($modelConfig['edit']) ?
                    $this->mergeFormConfig($modelConfig['form'], $modelConfig['edit']) : $modelConfig['form'];
            }

            $backendConfig['models'][$modelName] = $modelConfig;
        }

        return $backendConfig;
    }

    /**
     * Normalizes the view configuration when some of them doesn't define any
     * configuration.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeViewConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            // if the original 'search' config doesn't define its own DQL filter, use the one form 'list'
            if (!isset($modelConfig['search']) || !array_key_exists('dql_filter', $modelConfig['search'])) {
                $modelConfig['search']['dql_filter'] = $modelConfig['list']['dql_filter'] ?? null;
            }

            foreach (['edit', 'form', 'list', 'new', 'search', 'show'] as $view) {
                $modelConfig[$view] = array_replace_recursive($this->defaultViewConfig[$view],
                    $modelConfig[$view] ?? []);
            }

            $backendConfig['models'][$modelName] = $modelConfig;
        }

        return $backendConfig;
    }

    /**
     * Fields can be defined using two different formats:.
     * # Config format #1: simple configuration
     * harmony_admin:
     *     Client:
     *         # ...
     *         list:
     *             fields: ['id', 'name', 'email']
     * # Config format #2: extended configuration
     * harmony_admin:
     *     Client:
     *         # ...
     *         list:
     *             fields: ['id', 'name', { property: 'email', label: 'Contact' }]
     * This method processes both formats to produce a common form field configuration
     * format used in the rest of the application.
     *
     * @param array $backendConfig
     *
     * @return array
     * @throws \RuntimeException
     */
    private function normalizePropertyConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            $designElementIndex = 0;
            foreach (['form', 'edit', 'list', 'new', 'search', 'show'] as $view) {
                $fields = [];
                foreach ($modelConfig[$view]['fields'] as $i => $field) {
                    if (!is_string($field) && !is_array($field)) {
                        throw new \RuntimeException(sprintf('The values of the "fields" option for the "%s" view of the "%s" model can only be strings or arrays.',
                            $view, $modelConfig['class']));
                    }

                    if (is_string($field)) {
                        // Config format #1: field is just a string representing the model property
                        $fieldConfig = ['property' => $field];
                    } else {
                        // Config format #1: field is an array that defines one or more
                        // options. Check that either 'property' or 'type' option is set
                        if (!array_key_exists('property', $field) && !array_key_exists('type', $field)) {
                            throw new \RuntimeException(sprintf('One of the values of the "fields" option for the "%s" view of the "%s" model does not define neither of the mandatory options ("property" or "type").',
                                $view, $modelConfig['class']));
                        }

                        $fieldConfig = $field;
                    }

                    // for 'image' type fields, if the model defines an 'image_base_path'
                    // option, but the field does not, use the value defined by the model
                    if (isset($fieldConfig['type']) && 'image' === $fieldConfig['type']) {
                        if (!isset($fieldConfig['base_path']) && isset($modelConfig['image_base_path'])) {
                            $fieldConfig['base_path'] = $modelConfig['image_base_path'];
                        }
                    }

                    // for 'file' type fields, if the model defines an 'file_base_path'
                    // option, but the field does not, use the value defined by the model
                    if (isset($fieldConfig['type']) && 'file' === $fieldConfig['type']) {
                        if (!isset($fieldConfig['base_path']) && isset($modelConfig['file_base_path'])) {
                            $fieldConfig['base_path'] = $modelConfig['file_base_path'];
                        }
                    }

                    // fields that don't define the 'property' name are special form design elements
                    $fieldName          = $fieldConfig['property'] ??
                        '_harmony_admin_form_design_element_' . $designElementIndex;
                    $fields[$fieldName] = $fieldConfig;
                    ++ $designElementIndex;
                }

                $backendConfig['models'][$modelName][$view]['fields'] = $fields;
            }
        }

        return $backendConfig;
    }

    /**
     * Normalizes the configuration of the special elements that forms may include
     * to create advanced designs (such as dividers and fieldsets).
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeFormDesignConfig(array $backendConfig)
    {
        // edge case: if the first 'group' type is not the first form field,
        // all the previous form fields are "ungrouped". To avoid design issues,
        // insert an empty 'group' type (no label, no icon) as the first form element.
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['form', 'edit', 'new'] as $view) {
                $fieldNumber = 0;

                foreach ($modelConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    ++ $fieldNumber;
                    $isFormDesignElement = !isset($fieldConfig['property']) && isset($fieldConfig['type']);

                    if ($isFormDesignElement && 'tab' === $fieldConfig['type']) {
                        if ($fieldNumber > 1) {
                            $backendConfig['models'][$modelName][$view]['fields']
                                = array_merge(['_harmony_admin_form_design_element_forced_first_tab' => ['type' => 'tab']],
                                $backendConfig['models'][$modelName][$view]['fields']);
                        }
                        break;
                    }
                }

                $fieldNumber            = 0;
                $previousTabFieldNumber = - 1;
                $isTheFirstGroupElement = true;

                foreach ($modelConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    ++ $fieldNumber;
                    $isFormDesignElement = !isset($fieldConfig['property']) && isset($fieldConfig['type']);

                    if ($isFormDesignElement && 'tab' === $fieldConfig['type']) {
                        $previousTabFieldNumber = $fieldNumber;
                        $isTheFirstGroupElement = true;
                    } elseif ($isFormDesignElement && 'group' === $fieldConfig['type']) {
                        if ($isTheFirstGroupElement && - 1 === $previousTabFieldNumber && $fieldNumber > 1) {
                            // if no tab is used, insert the group at the beginning of the array
                            $backendConfig['models'][$modelName][$view]['fields']
                                = array_merge(['_harmony_admin_form_design_element_forced_first_group' => ['type' => 'group']],
                                $backendConfig['models'][$modelName][$view]['fields']);
                            break;
                        } elseif ($isTheFirstGroupElement && $previousTabFieldNumber >= 0 &&
                            $fieldNumber > $previousTabFieldNumber + 1) {
                            // if tabs are used, we insert the group after the previous tab field into the array
                            $backendConfig['models'][$modelName][$view]['fields']
                                = array_merge(array_slice($backendConfig['models'][$modelName][$view]['fields'], 0,
                                $previousTabFieldNumber, true), [
                                '_harmony_admin_form_design_element_forced_group_' . $fieldNumber => ['type' => 'group']
                            ], \array_slice($backendConfig['models'][$modelName][$view]['fields'],
                                $previousTabFieldNumber, null, true));
                        }

                        $isTheFirstGroupElement = false;
                    }
                }
            }
        }

        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['form', 'edit', 'new'] as $view) {
                foreach ($modelConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    // this is a form design element instead of a regular property
                    $isFormDesignElement = !isset($fieldConfig['property']) && isset($fieldConfig['type']);
                    if ($isFormDesignElement &&
                        in_array($fieldConfig['type'], ['divider', 'group', 'section', 'tab'])) {
                        // assign them a property name to add them later as unmapped form fields
                        $fieldConfig['property'] = $fieldName;

                        if ('tab' === $fieldConfig['type'] && empty($fieldConfig['id'])) {
                            // ensures unique IDs like '_harmony_admin_form_design_element_0'
                            $fieldConfig['id'] = $fieldConfig['property'];
                        }

                        // transform the form type shortcuts into the real form type short names
                        $fieldConfig['type'] = 'harmony_admin_' . $fieldConfig['type'];
                    }

                    $backendConfig['models'][$modelName][$view]['fields'][$fieldName] = $fieldConfig;
                }
            }
        }

        return $backendConfig;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeActionConfig(array $backendConfig)
    {
        $views = ['edit', 'list', 'new', 'show', 'form'];

        foreach ($views as $view) {
            if (!isset($backendConfig[$view]['actions'])) {
                $backendConfig[$view]['actions'] = [];
            }

            // there is no need to check if the "actions" option for the global
            // view is an array because it's done by the Configuration definition
        }

        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach ($views as $view) {
                if (!isset($modelConfig[$view]['actions'])) {
                    $backendConfig['models'][$modelName][$view]['actions'] = [];
                }

                if (!is_array($backendConfig['models'][$modelName][$view]['actions'])) {
                    throw new \InvalidArgumentException(sprintf('The "actions" configuration for the "%s" view of the "%s" model must be an array (a string was provided).',
                        $view, $modelName));
                }
            }
        }

        return $backendConfig;
    }

    /**
     * It processes the optional 'controller' config option to check if the
     * given controller exists (it doesn't matter if it's a normal controller
     * or if it's defined as a service).
     *
     * @param array $backendConfig
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    private function normalizeControllerConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            if (isset($modelConfig['controller'])) {
                $controller = trim($modelConfig['controller']);

                if (!$this->container->has($controller) && !class_exists($controller)) {
                    throw new \InvalidArgumentException(sprintf('The "%s" value defined in the "controller" option of the "%s" model is not a valid controller. For a regular controller, set its FQCN as the value; for a controller defined as service, set its service name as the value.',
                        $controller, $modelName));
                }

                $backendConfig['models'][$modelName]['controller'] = $controller;
            }
        }

        return $backendConfig;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeTranslationConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            if (!isset($modelConfig['translation_domain'])) {
                $modelConfig['translation_domain'] = $backendConfig['translation_domain'];
            }

            if ('' === $modelConfig['translation_domain']) {
                throw new \InvalidArgumentException(sprintf('The value defined in the "translation_domain" option of the "%s" model is not a valid translation domain name (use false to disable translations).',
                    $modelName));
            }

            $backendConfig['models'][$modelName] = $modelConfig;
        }

        return $backendConfig;
    }

    /**
     * Merges the form configuration recursively from the 'form' view to the
     * 'edit' and 'new' views. It processes the configuration of the form fields
     * in a special way to keep all their configuration and allow overriding and
     * removing of fields.
     *
     * @param array $parentConfig The config of the 'form' view
     * @param array $childConfig  The config of the 'edit' and 'new' views
     *
     * @return array
     */
    private function mergeFormConfig(array $parentConfig, array $childConfig)
    {
        // save the fields config for later processing
        $parentFields      = $parentConfig['fields'] ?? [];
        $childFields       = $childConfig['fields'] ?? [];
        $removedFieldNames = $this->getRemovedFieldNames($childFields);

        // first, perform a recursive replace to merge both configs
        $mergedConfig = array_replace_recursive($parentConfig, $childConfig);

        // merge the config of each field individually
        $mergedFields = [];
        foreach ($parentFields as $parentFieldName => $parentFieldConfig) {
            if (isset($parentFieldConfig['property']) && in_array($parentFieldConfig['property'], $removedFieldNames)) {
                continue;
            }

            if (!isset($parentFieldConfig['property'])) {
                // this isn't a regular form field but a special design element (group, section, divider); add it
                $mergedFields[$parentFieldName] = $parentFieldConfig;
                continue;
            }

            $childFieldConfig               = $this->findFieldConfigByProperty($childFields,
                $parentFieldConfig['property']) ?: [];
            $mergedFields[$parentFieldName] = array_replace_recursive($parentFieldConfig, $childFieldConfig);
        }

        // add back the fields that are defined in child config but not in parent config
        foreach ($childFields as $childFieldName => $childFieldConfig) {
            $isFormDesignElement  = !isset($childFieldConfig['property']);
            $isNotRemovedField    = isset($childFieldConfig['property']) &&
                0 !== strpos($childFieldConfig['property'], '-');
            $isNotAlreadyIncluded = isset($childFieldConfig['property']) &&
                !array_key_exists($childFieldConfig['property'], $mergedFields);

            if ($isFormDesignElement || ($isNotRemovedField && $isNotAlreadyIncluded)) {
                $mergedFields[$childFieldName] = $childFieldConfig;
            }
        }

        // finally, copy the processed field config into the merged config
        $mergedConfig['fields'] = $mergedFields;

        return $mergedConfig;
    }

    /**
     * The 'edit' and 'new' views can remove fields defined in the 'form' view
     * by defining fields with a '-' dash at the beginning of its name (e.g.
     * { property: '-name' } to remove the 'name' property).
     *
     * @param array $fieldsConfig
     *
     * @return array
     */
    private function getRemovedFieldNames(array $fieldsConfig)
    {
        $removedFieldNames = [];
        foreach ($fieldsConfig as $fieldConfig) {
            if (isset($fieldConfig['property']) && 0 === strpos($fieldConfig['property'], '-')) {
                $removedFieldNames[] = mb_substr($fieldConfig['property'], 1);
            }
        }

        return $removedFieldNames;
    }

    /**
     * @param array $fieldsConfig
     * @param       $propertyName
     *
     * @return mixed|null
     */
    private function findFieldConfigByProperty(array $fieldsConfig, $propertyName)
    {
        foreach ($fieldsConfig as $fieldConfig) {
            if (isset($fieldConfig['property']) && $propertyName === $fieldConfig['property']) {
                return $fieldConfig;
            }
        }

        return null;
    }
}
