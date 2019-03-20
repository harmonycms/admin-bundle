<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Configuration;

use Harmony\Bundle\AdminBundle\Form\Util\FormTypeHelper;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use function array_intersect_key;
use function array_key_exists;
use function array_merge;
use function array_replace;
use function array_replace_recursive;
use function in_array;
use function mb_substr;

/**
 * Processes the model fields to complete their configuration and to treat
 * some fields in a special way.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class PropertyConfigPass implements ConfigPassInterface
{

    /** @var array $defaultModelFieldConfig */
    private $defaultModelFieldConfig
        = [
            // CSS class or classes applied to form field or list/show property
            'css_class'    => '',
            // date/time/datetime/number format applied to form field value
            'format'       => null,
            // form field help message
            'help'         => null,
            // form field label (if 'null', autogenerate it)
            'label'        => null,
            // its value matches the value of 'dataType' for list/show and the value of 'fieldType' for new/edit
            'type'         => null,
            // Symfony form field type (text, date, number, choice, ...) used to display the field
            'fieldType'    => null,
            // Data type (text, date, integer, boolean, ...) of the Doctrine property associated with the field
            'dataType'     => null,
            // is a virtual field or a real Doctrine model property?
            'virtual'      => false,
            // listings can be sorted according to the values of this field
            'sortable'     => true,
            // the path of the template used to render the field in 'show' and 'list' views
            'template'     => null,
            // the options passed to the Symfony Form type used to render the form field
            'type_options' => [],
            // the name of the group where this form field is displayed (used only for complex form layouts)
            'form_group'   => null,
        ];

    /** @var array $defaultVirtualFieldMetadata */
    private $defaultVirtualFieldMetadata
        = [
            'columnName'   => 'virtual',
            'fieldName'    => 'virtual',
            'id'           => false,
            'length'       => null,
            'nullable'     => false,
            'precision'    => 0,
            'scale'        => 0,
            'sortable'     => false,
            'type'         => 'text',
            'type_options' => [
                'required' => false,
            ],
            'unique'       => false,
            'virtual'      => true,
        ];

    /** @var FormRegistryInterface $formRegistry */
    private $formRegistry;

    /**
     * PropertyConfigPass constructor.
     *
     * @param FormRegistryInterface $formRegistry
     */
    public function __construct(FormRegistryInterface $formRegistry)
    {
        $this->formRegistry = $formRegistry;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig)
    {
        $backendConfig = $this->processMetadataConfig($backendConfig);
        $backendConfig = $this->processFieldConfig($backendConfig);

        return $backendConfig;
    }

    /**
     * $modelConfig['properties'] stores the raw metadata provided by Doctrine.
     * This method adds some other options needed for HarmonyAdmin backends. This is
     * required because $modelConfig['properties'] will be used as the fields of
     * the views that don't define their fields.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processMetadataConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            $properties = [];
            foreach ($modelConfig['properties'] as $propertyName => $propertyMetadata) {
                $typeGuess     = $this->getFormTypeGuessOfProperty($modelConfig['class'], $propertyName);
                $requiredGuess = $this->getFormRequiredGuessOfProperty($modelConfig['class'], $propertyName);

                $guessedType = null !== $typeGuess ? FormTypeHelper::getTypeName($typeGuess->getType()) :
                    $propertyMetadata['type'];

                $guessedTypeOptions = null !== $typeGuess ? $typeGuess->getOptions() : [];

                if (null !== $requiredGuess) {
                    $guessedTypeOptions['required'] = $requiredGuess->getValue();
                }

                $properties[$propertyName] = array_replace($this->defaultModelFieldConfig, $propertyMetadata, [
                    'property'     => $propertyName,
                    'dataType'     => $propertyMetadata['type'],
                    'fieldType'    => $guessedType,
                    'type_options' => $guessedTypeOptions,
                ]);

                // 'boolean' properties are displayed by default as toggleable
                // flip switches (if the 'edit' action is enabled for the model)
                if ('boolean' === $properties[$propertyName]['dataType'] &&
                    !in_array('edit', $modelConfig['disabled_actions'])) {
                    $properties[$propertyName]['dataType'] = 'toggle';
                }
            }

            $backendConfig['models'][$modelName]['properties'] = $properties;
        }

        return $backendConfig;
    }

    /**
     * Completes the configuration of each field/property with the metadata
     * provided by Doctrine for each model property.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processFieldConfig(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            foreach (['edit', 'list', 'new', 'search', 'show'] as $view) {
                $originalViewConfig = $backendConfig['models'][$modelName][$view];
                foreach ($modelConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    $originalFieldConfig = isset($originalViewConfig['fields'][$fieldName]) ?
                        $originalViewConfig['fields'][$fieldName] : null;

                    if (array_key_exists($fieldName, $modelConfig['properties'])) {
                        $fieldMetadata = array_merge($modelConfig['properties'][$fieldName], ['virtual' => false]);
                    } else {
                        // this is a virtual field which doesn't exist as a property of
                        // the related model. That's why Doctrine can't provide metadata for it
                        $fieldMetadata = array_merge($this->defaultVirtualFieldMetadata,
                            ['columnName' => $fieldName, 'fieldName' => $fieldName]);
                    }

                    $normalizedConfig = array_replace_recursive($this->defaultModelFieldConfig, $fieldMetadata,
                        $fieldConfig);

                    // 'list', 'search' and 'show' views: use the value of the 'type' option
                    // as the 'dataType' option because the previous code has already
                    // prioritized end-user preferences over Doctrine and default values
                    if (in_array($view, ['list', 'search', 'show'])) {
                        $normalizedConfig['dataType'] = $normalizedConfig['type'];
                    }

                    // 'new' and 'edit' views: if the user has defined the 'type' option
                    // for the field, use it as 'fieldType'. Otherwise, use the guessed
                    // form type of the property data type.
                    if (in_array($view, ['edit', 'new'])) {
                        $normalizedConfig['fieldType'] = isset($originalFieldConfig['type']) ?
                            $originalFieldConfig['type'] : $normalizedConfig['fieldType'];

                        if (null === $normalizedConfig['fieldType']) {
                            // this is a virtual field which doesn't exist as a property of
                            // the related model. Textarea is used as a default form type.
                            $normalizedConfig['fieldType'] = 'textarea';
                        }

                        $normalizedConfig['type_options'] = $this->getFormTypeOptionsOfProperty($normalizedConfig,
                            $fieldMetadata, $originalFieldConfig);
                    }

                    // special case for the 'list' view: 'boolean' properties are displayed
                    // as toggleable flip switches when certain conditions are met
                    if ('list' === $view && 'boolean' === $normalizedConfig['dataType']) {
                        // conditions:
                        //   1) the end-user hasn't configured the field type explicitly
                        //   2) the 'edit' action is enabled for the 'list' view of this model
                        if (!isset($originalFieldConfig['type']) &&
                            !in_array('edit', $modelConfig['disabled_actions'])) {
                            $normalizedConfig['dataType'] = 'toggle';
                        }
                    }

                    if (null === $normalizedConfig['format']) {
                        $normalizedConfig['format'] = $this->getFieldFormat($normalizedConfig['type'], $backendConfig);
                    }

                    $backendConfig['models'][$modelName][$view]['fields'][$fieldName] = $normalizedConfig;
                }
            }
        }

        return $backendConfig;
    }

    /**
     * Resolves from type options of field
     *
     * @param array $mergedConfig
     * @param array $guessedConfig
     * @param array $userDefinedConfig
     *
     * @return array
     */
    private function getFormTypeOptionsOfProperty(array $mergedConfig, array $guessedConfig, array $userDefinedConfig)
    {
        $resolvedFormOptions = $mergedConfig['type_options'];

        // if the user has defined a 'type', the type options
        // must be reset so they don't get mixed with the form components guess.
        // Only the 'required' and user defined option are kept
        if (isset($userDefinedConfig['type'], $guessedConfig['fieldType']) &&
            $userDefinedConfig['type'] !== $guessedConfig['fieldType']) {
            $resolvedFormOptions = array_merge(array_intersect_key($resolvedFormOptions, ['required' => null]),
                isset($userDefinedConfig['type_options']) ? $userDefinedConfig['type_options'] : []);
        }
        // if the user has defined the "type" or "type_options"
        // AND the "type" is the same as the default one
        elseif ((isset($userDefinedConfig['type']) && isset($guessedConfig['fieldType']) &&
                $userDefinedConfig['type'] === $guessedConfig['fieldType']) ||
            (!isset($userDefinedConfig['type']) && isset($userDefinedConfig['type_options']))) {
            $resolvedFormOptions = array_merge($resolvedFormOptions,
                isset($userDefinedConfig['type_options']) ? $userDefinedConfig['type_options'] : []);
        }

        return $resolvedFormOptions;
    }

    /**
     * Guesses what Form Type a property of a class has.
     *
     * @param string $class
     * @param string $property
     *
     * @return TypeGuess|null
     */
    private function getFormTypeGuessOfProperty($class, $property)
    {
        return $this->formRegistry->getTypeGuesser()->guessType($class, $property);
    }

    /**
     * Guesses if a property of a class should be a required field in a Form.
     *
     * @param string $class
     * @param string $property
     *
     * @return ValueGuess|null
     */
    private function getFormRequiredGuessOfProperty($class, $property)
    {
        return $this->formRegistry->getTypeGuesser()->guessRequired($class, $property);
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
}
