<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Twig;

use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Harmony\Bundle\AdminBundle\Router\HarmonyAdminRouter;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Defines the filters and functions used to render the bundle's templates.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class HarmonyAdminTwigExtension extends AbstractExtension
{

    /** @var ConfigManager $configManager */
    private $configManager;

    /** @var PropertyAccessor $propertyAccessor */
    private $propertyAccessor;

    /** @var HarmonyAdminRouter $harmonyAdminRouter */
    private $harmonyAdminRouter;

    /** @var bool $debug */
    private $debug = false;

    /** @var LogoutUrlGenerator $logoutUrlGenerator */
    private $logoutUrlGenerator;

    /**
     * HarmonyAdminTwigExtension constructor.
     *
     * @param ConfigManager      $configManager
     * @param PropertyAccessor   $propertyAccessor
     * @param HarmonyAdminRouter $harmonyAdminRouter
     * @param bool               $debug
     * @param LogoutUrlGenerator $logoutUrlGenerator
     */
    public function __construct(ConfigManager $configManager, PropertyAccessor $propertyAccessor,
                                HarmonyAdminRouter $harmonyAdminRouter, bool $debug,
                                LogoutUrlGenerator $logoutUrlGenerator)
    {
        $this->configManager      = $configManager;
        $this->propertyAccessor   = $propertyAccessor;
        $this->harmonyAdminRouter = $harmonyAdminRouter;
        $this->debug              = $debug;
        $this->logoutUrlGenerator = $logoutUrlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('harmony_admin_render_field_for_*_view', [$this, 'renderModelField'],
                ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('harmony_admin_config', [$this, 'getBackendConfiguration']),
            new TwigFunction('harmony_admin_model', [$this, 'getModelConfiguration']),
            new TwigFunction('harmony_admin_path', [$this, 'getModelPath']),
            new TwigFunction('harmony_admin_action_is_enabled', [$this, 'isActionEnabled']),
            new TwigFunction('harmony_admin_action_is_enabled_for_*_view', [$this, 'isActionEnabled']),
            new TwigFunction('harmony_admin_get_action', [$this, 'getActionConfiguration']),
            new TwigFunction('harmony_admin_get_action_for_*_view', [$this, 'getActionConfiguration']),
            new TwigFunction('harmony_admin_get_actions_for_*_item', [$this, 'getActionsForItem']),
            new TwigFunction('harmony_admin_logout_path', [$this, 'getLogoutPath']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('harmony_admin_truncate', [$this, 'truncateText'], ['needs_environment' => true]),
            new TwigFilter('harmony_admin_urldecode', 'urldecode'),
        ];
    }

    /**
     * Returns the entire backend configuration or the value corresponding to
     * the provided key. The dots of the key are automatically transformed into
     * nested keys. Example: 'assets.css' => $config['assets']['css'].
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function getBackendConfiguration($key = null)
    {
        return $this->configManager->getBackendConfig($key);
    }

    /**
     * Returns the entire configuration of the given model.
     *
     * @param string $modelName
     *
     * @return array|null
     */
    public function getModelConfiguration($modelName)
    {
        return null !== $this->getBackendConfiguration('models.' . $modelName) ?
            $this->configManager->getModelConfig($modelName) : null;
    }

    /**
     * @param object|string $model
     * @param string        $action
     * @param array         $parameters
     *
     * @return string
     */
    public function getModelPath($model, $action, array $parameters = [])
    {
        return $this->harmonyAdminRouter->generate($model, $action, $parameters);
    }

    /**
     * Renders the value stored in a property/field of the given model. This
     * function contains a lot of code protections to avoid errors when the
     * property doesn't exist or its value is not accessible. This ensures that
     * the function never generates a warning or error message when calling it.
     *
     * @param Environment $twig
     * @param string      $view          The view in which the item is being rendered
     * @param string      $modelName     The name of the model associated with the item
     * @param object      $item          The item which is being rendered
     * @param array       $fieldMetadata The metadata of the actual field being rendered
     *
     * @return string
     * @throws \Exception
     */
    public function renderModelField(Environment $twig, $view, $modelName, $item, array $fieldMetadata)
    {
        $modelConfig        = $this->configManager->getModelConfig($modelName);
        $hasCustomTemplate  = 0 !== strpos($fieldMetadata['template'], '@HarmonyAdmin/');
        $templateParameters = [];

        try {
            $templateParameters = $this->getTemplateParameters($modelName, $view, $fieldMetadata, $item);

            // if the field defines a custom template, render it (no matter if the value is null or inaccessible)
            if ($hasCustomTemplate) {
                return $twig->render($fieldMetadata['template'], $templateParameters);
            }

            if (false === $templateParameters['is_accessible']) {
                return $twig->render($modelConfig['templates']['label_inaccessible'], $templateParameters);
            }

            if (null === $templateParameters['value']) {
                return $twig->render($modelConfig['templates']['label_null'], $templateParameters);
            }

            if (empty($templateParameters['value']) &&
                \in_array($fieldMetadata['dataType'], ['image', 'file', 'array', 'simple_array'])) {
                return $twig->render($templateParameters['model_config']['templates']['label_empty'],
                    $templateParameters);
            }

            return $twig->render($fieldMetadata['template'], $templateParameters);
        }
        catch (\Exception $e) {
            if ($this->debug) {
                throw $e;
            }

            return $twig->render($modelConfig['templates']['label_undefined'], $templateParameters);
        }
    }

    /**
     * Checks whether the given 'action' is enabled for the given 'model'.
     *
     * @param string $view
     * @param string $action
     * @param string $modelName
     *
     * @return bool
     */
    public function isActionEnabled($view, $action, $modelName): bool
    {
        return $this->configManager->isActionEnabled($modelName, $view, $action);
    }

    /**
     * Returns the full action configuration for the given 'model' and 'view'.
     *
     * @param string $view
     * @param string $action
     * @param string $modelName
     *
     * @return array
     */
    public function getActionConfiguration($view, $action, $modelName): array
    {
        return $this->configManager->getActionConfig($modelName, $view, $action);
    }

    /**
     * Returns the actions configured for each item displayed in the given view.
     * This method is needed because some actions are displayed globally for the
     * entire view (e.g. 'new' action in 'list' view).
     *
     * @param string $view
     * @param string $modelName
     *
     * @return array
     */
    public function getActionsForItem($view, $modelName): array
    {
        try {
            $modelConfig = $this->configManager->getModelConfig($modelName);
        }
        catch (\Exception $e) {
            return [];
        }

        $disabledActions = $modelConfig['disabled_actions'];
        $viewActions     = $modelConfig[$view]['actions'];

        $actionsExcludedForItems = [
            'list' => ['new', 'search'],
            'edit' => [],
            'new'  => [],
            'show' => [],
        ];
        $excludedActions         = $actionsExcludedForItems[$view];

        return array_filter($viewActions, function ($action) use ($excludedActions, $disabledActions) {
            return !\in_array($action['name'], $excludedActions) && !\in_array($action['name'], $disabledActions);
        });
    }

    /**
     * Copied from the official Text Twig extension.
     * code: https://github.com/twigphp/Twig-extensions/blob/master/lib/Twig/Extensions/Extension/Text.php
     * author: Henrik Bjornskov <hb@peytz.dk>
     * copyright holder: (c) 2009 Fabien Potencier
     *
     * @param Environment       $env
     * @param                   $value
     * @param int               $length
     * @param bool              $preserve
     * @param string            $separator
     *
     * @return string
     */
    public function truncateText(Environment $env, $value, $length = 64, $preserve = false, $separator = '...'): string
    {
        try {
            $value = (string)$value;
        }
        catch (\Exception $e) {
            $value = '';
        }

        if (mb_strlen($value, $env->getCharset()) > $length) {
            if ($preserve) {
                // If breakpoint is on the last word, return the value without separator.
                if (false === ($breakpoint = mb_strpos($value, ' ', $length, $env->getCharset()))) {
                    return $value;
                }

                $length = $breakpoint;
            }

            return rtrim(mb_substr($value, 0, $length, $env->getCharset())) . $separator;
        }

        return $value;
    }

    /**
     * This reimplementation of Symfony's logout_path() helper is needed because
     * when no arguments are passed to the getLogoutPath(), it's common to get
     * exceptions and there is no way to recover from them in a Twig template.
     *
     * @return null|string
     */
    public function getLogoutPath(): ?string
    {
        if (null === $this->logoutUrlGenerator) {
            return null;
        }

        try {
            return $this->logoutUrlGenerator->getLogoutPath();
        }
        catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param       $modelName
     * @param       $view
     * @param array $fieldMetadata
     * @param       $item
     *
     * @return array
     */
    private function getTemplateParameters($modelName, $view, array $fieldMetadata, $item): array
    {
        $fieldName = $fieldMetadata['property'];
        $fieldType = $fieldMetadata['dataType'];

        $parameters = [
            'backend_config' => $this->getBackendConfiguration(),
            'model_config'   => $this->configManager->getModelConfig($modelName),
            'field_options'  => $fieldMetadata,
            'item'           => $item,
            'view'           => $view,
        ];

        if ($this->propertyAccessor->isReadable($item, $fieldName)) {
            $parameters['value']         = $this->propertyAccessor->getValue($item, $fieldName);
            $parameters['is_accessible'] = true;
        } else {
            $parameters['value']         = null;
            $parameters['is_accessible'] = false;
        }

        if ('image' === $fieldType) {
            $parameters = $this->addImageFieldParameters($parameters);
        }

        if ('file' === $fieldType) {
            $parameters = $this->addFileFieldParameters($parameters);
        }

        if ('association' === $fieldType) {
            $parameters = $this->addAssociationFieldParameters($parameters);
        }

        // when a virtual field doesn't define it's type, consider it a string
        if (true === $fieldMetadata['virtual'] && null === $parameters['field_options']['dataType']) {
            $parameters['value'] = (string)$parameters['value'];
        }

        return $parameters;
    }

    /**
     * @param array $templateParameters
     *
     * @return array
     */
    private function addImageFieldParameters(array $templateParameters): array
    {
        // add the base path only to images that are not absolute URLs (http or https) or protocol-relative URLs (//)
        if (null !== $templateParameters['value'] &&
            0 === preg_match('/^(http[s]?|\/\/)/i', $templateParameters['value'])) {
            $templateParameters['value'] = isset($templateParameters['field_options']['base_path']) ?
                rtrim($templateParameters['field_options']['base_path'], '/') . '/' .
                ltrim($templateParameters['value'], '/') : '/' . ltrim($templateParameters['value'], '/');
        }

        $templateParameters['uuid'] = md5($templateParameters['value']);

        return $templateParameters;
    }

    /**
     * @param array $templateParameters
     *
     * @return array
     */
    private function addFileFieldParameters(array $templateParameters): array
    {
        // add the base path only to files that are not absolute URLs (http or https) or protocol-relative URLs (//)
        if (null !== $templateParameters['value'] &&
            0 === preg_match('/^(http[s]?|\/\/)/i', $templateParameters['value'])) {
            $templateParameters['value'] = isset($templateParameters['field_options']['base_path']) ?
                rtrim($templateParameters['field_options']['base_path'], '/') . '/' .
                ltrim($templateParameters['value'], '/') : '/' . ltrim($templateParameters['value'], '/');
        }

        $templateParameters['filename'] = $templateParameters['field_options']['filename'] ??
            basename($templateParameters['value']);

        return $templateParameters;
    }

    /**
     * @param array $templateParameters
     *
     * @return array
     */
    private function addAssociationFieldParameters(array $templateParameters): array
    {
        $targetModelConfig
            = $this->configManager->getModelConfigByClass($templateParameters['field_options']['targetEntity']);
        // the associated model is not managed by HarmonyAdmin
        if (null === $targetModelConfig) {
            return $templateParameters;
        }

        $isShowActionAllowed = !\in_array('show', $targetModelConfig['disabled_actions']);

        if ($templateParameters['field_options']['associationType'] & 3) {
            if ($this->propertyAccessor->isReadable($templateParameters['value'],
                $targetModelConfig['primary_key_field_name'])) {
                $primaryKeyValue = $this->propertyAccessor->getValue($templateParameters['value'],
                    $targetModelConfig['primary_key_field_name']);
            } else {
                $primaryKeyValue = null;
            }

            // get the string representation of the associated *-to-one model
            if (method_exists($templateParameters['value'], '__toString')) {
                $templateParameters['value'] = (string)$templateParameters['value'];
            } elseif (null !== $primaryKeyValue) {
                $templateParameters['value'] = sprintf('%s #%s', $targetModelConfig['name'], $primaryKeyValue);
            } else {
                $templateParameters['value'] = null;
            }

            // if the associated model is managed by HarmonyAdmin, and the "show"
            // action is enabled for the associated model, display a link to it
            if (null !== $targetModelConfig && null !== $primaryKeyValue && $isShowActionAllowed) {
                $templateParameters['link_parameters'] = [
                    'action' => 'show',
                    'model'  => $targetModelConfig['name'],
                    // casting to string is needed because models can use objects as primary keys
                    'id'     => (string)$primaryKeyValue,
                ];
            }
        }

        if ($templateParameters['field_options']['associationType'] & 12) {
            // if the associated model is managed by HarmonyAdmin, and the "show"
            // action is enabled for the associated model, display a link to it
            if (null !== $targetModelConfig && $isShowActionAllowed) {
                $templateParameters['link_parameters'] = [
                    'action'           => 'show',
                    'model'            => $targetModelConfig['name'],
                    'primary_key_name' => $targetModelConfig['primary_key_field_name'],
                ];
            }
        }

        return $templateParameters;
    }
}
