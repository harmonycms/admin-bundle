<?php

namespace Harmony\Bundle\AdminBundle\Configuration;

use Symfony\Component\Finder\Finder;
use Twig\Loader\FilesystemLoader;

/**
 * Processes the template configuration to decide which template to use to
 * display each property in each view. It also processes the global templates
 * used when there is no entity configuration (e.g. for error pages).
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class TemplateConfigPass implements ConfigPassInterface
{

    /** @var FilesystemLoader $twigLoader */
    private $twigLoader;

    /** @var array $defaultBackendTemplates */
    private $defaultBackendTemplates
        = [
            'layout'             => '@HarmonyAdmin/default/layout.html.twig',
            'menu'               => '@HarmonyAdmin/default/menu.html.twig',
            'edit'               => '@HarmonyAdmin/default/edit.html.twig',
            'list'               => '@HarmonyAdmin/default/list.html.twig',
            'new'                => '@HarmonyAdmin/default/new.html.twig',
            'show'               => '@HarmonyAdmin/default/show.html.twig',
            'exception'          => '@HarmonyAdmin/default/exception.html.twig',
            'flash_messages'     => '@HarmonyAdmin/default/flash_messages.html.twig',
            'paginator'          => '@HarmonyAdmin/default/paginator.html.twig',
            // fields
            'field_array'        => '@HarmonyAdmin/fields/field_array.html.twig',
            'field_association'  => '@HarmonyAdmin/fields/field_association.html.twig',
            'field_bigint'       => '@HarmonyAdmin/fields/field_bigint.html.twig',
            'field_boolean'      => '@HarmonyAdmin/fields/field_boolean.html.twig',
            'field_date'         => '@HarmonyAdmin/fields/field_date.html.twig',
            'field_dateinterval' => '@HarmonyAdmin/fields/field_dateinterval.html.twig',
            'field_datetime'     => '@HarmonyAdmin/fields/field_datetime.html.twig',
            'field_datetimetz'   => '@HarmonyAdmin/fields/field_datetimetz.html.twig',
            'field_decimal'      => '@HarmonyAdmin/fields/field_decimal.html.twig',
            'field_email'        => '@HarmonyAdmin/fields/field_email.html.twig',
            'field_file'         => '@HarmonyAdmin/fields/field_file.html.twig',
            'field_float'        => '@HarmonyAdmin/fields/field_float.html.twig',
            'field_guid'         => '@HarmonyAdmin/fields/field_guid.html.twig',
            'field_id'           => '@HarmonyAdmin/fields/field_id.html.twig',
            'field_image'        => '@HarmonyAdmin/fields/field_image.html.twig',
            'field_json'         => '@HarmonyAdmin/fields/field_json.html.twig',
            'field_json_array'   => '@HarmonyAdmin/fields/field_json_array.html.twig',
            'field_integer'      => '@HarmonyAdmin/fields/field_integer.html.twig',
            'field_object'       => '@HarmonyAdmin/fields/field_object.html.twig',
            'field_percent'      => '@HarmonyAdmin/fields/field_percent.html.twig',
            'field_raw'          => '@HarmonyAdmin/fields/field_raw.html.twig',
            'field_simple_array' => '@HarmonyAdmin/fields/field_simple_array.html.twig',
            'field_smallint'     => '@HarmonyAdmin/fields/field_smallint.html.twig',
            'field_string'       => '@HarmonyAdmin/fields/field_string.html.twig',
            'field_tel'          => '@HarmonyAdmin/fields/field_tel.html.twig',
            'field_text'         => '@HarmonyAdmin/fields/field_text.html.twig',
            'field_time'         => '@HarmonyAdmin/fields/field_time.html.twig',
            'field_toggle'       => '@HarmonyAdmin/fields/field_toggle.html.twig',
            'field_url'          => '@HarmonyAdmin/fields/field_url.html.twig',
            // labels
            'label_empty'        => '@HarmonyAdmin/default/label_empty.html.twig',
            'label_inaccessible' => '@HarmonyAdmin/default/label_inaccessible.html.twig',
            'label_null'         => '@HarmonyAdmin/default/label_null.html.twig',
            'label_undefined'    => '@HarmonyAdmin/default/label_undefined.html.twig',
        ];

    /** @var array $existingTemplates */
    private $existingTemplates = [];

    /**
     * TemplateConfigPass constructor.
     *
     * @param FilesystemLoader $twigLoader
     */
    public function __construct(FilesystemLoader $twigLoader)
    {
        $this->twigLoader = $twigLoader;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig): array
    {
        $backendConfig = $this->processEntityTemplates($backendConfig);
        $backendConfig = $this->processDefaultTemplates($backendConfig);
        $backendConfig = $this->processFieldTemplates($backendConfig);

        $this->existingTemplates = [];

        return $backendConfig;
    }

    /**
     * Determines the template used to render each backend element. This is not
     * trivial because templates can depend on the entity displayed and they
     * define an advanced override mechanism.
     *
     * @param array $backendConfig
     *
     * @return array
     * @throws \RuntimeException
     */
    private function processEntityTemplates(array $backendConfig)
    {
        // first, resolve the general template overriding mechanism
        // 1st level priority: harmony_admin.entities.<entityName>.templates.<templateName> config option
        // 2nd level priority: harmony_admin.design.templates.<templateName> config option
        // 3rd level priority: app/Resources/views/harmony_admin/<entityName>/<templateName>.html.twig
        // 4th level priority: app/Resources/views/harmony_admin/<templateName>.html.twig
        // 5th level priority: @HarmonyAdmin/default/<templateName>.html.twig
        foreach ($backendConfig['entities'] as $entityName => $entityConfig) {
            foreach ($this->defaultBackendTemplates as $templateName => $defaultTemplatePath) {
                $candidateTemplates = [
                    isset($entityConfig['templates'][$templateName]) ? $entityConfig['templates'][$templateName] : null,
                    isset($backendConfig['design']['templates'][$templateName]) ?
                        $backendConfig['design']['templates'][$templateName] : null,
                    'harmony_admin/' . $entityName . '/' . $templateName . '.html.twig',
                    'harmony_admin/' . $templateName . '.html.twig',
                ];
                $templatePath       = $this->findFirstExistingTemplate($candidateTemplates) ?: $defaultTemplatePath;

                if (null === $templatePath) {
                    throw new \RuntimeException(sprintf('None of the templates defined for the "%s" fragment of the "%s" entity exists (templates defined: %s).',
                        $templateName, $entityName, implode(', ', $candidateTemplates)));
                }

                $entityConfig['templates'][$templateName] = $templatePath;
            }

            $backendConfig['entities'][$entityName] = $entityConfig;
        }

        // second, walk through all entity fields to determine their specific template
        foreach ($backendConfig['entities'] as $entityName => $entityConfig) {
            foreach (['list', 'show'] as $view) {
                foreach ($entityConfig[$view]['fields'] as $fieldName => $fieldMetadata) {
                    // if the field defines its own template, resolve its location
                    if (isset($fieldMetadata['template'])) {
                        $templatePath = $fieldMetadata['template'];

                        // before considering $templatePath a regular Symfony template
                        // path, check if the given template exists in any of these directories:
                        // * app/Resources/views/harmony_admin/<entityName>/<templatePath>
                        // * app/Resources/views/harmony_admin/<templatePath>
                        $templatePath = $this->findFirstExistingTemplate([
                            'harmony_admin/' . $entityName . '/' . $templatePath,
                            'harmony_admin/' . $templatePath,
                            $templatePath,
                        ]);
                    } else {
                        // At this point, we don't know the exact data type associated with each field.
                        // The template is initialized to null and it will be resolved at runtime in the Configurator class
                        $templatePath = null;
                    }

                    $entityConfig[$view]['fields'][$fieldName]['template'] = $templatePath;
                }
            }

            $backendConfig['entities'][$entityName] = $entityConfig;
        }

        return $backendConfig;
    }

    /**
     * Determines the templates used to render each backend element when no
     * entity configuration is available. It's similar to processEntityTemplates()
     * but it doesn't take into account the details of each entity.
     * This is needed for example when an exception is triggered and no entity
     * configuration is available to know which template should be rendered.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processDefaultTemplates(array $backendConfig): array
    {
        // 1st level priority: harmony_admin.design.templates.<templateName> config option
        // 2nd level priority: app/Resources/views/harmony_admin/<templateName>.html.twig
        // 3rd level priority: @HarmonyAdmin/default/<templateName>.html.twig
        foreach ($this->defaultBackendTemplates as $templateName => $defaultTemplatePath) {
            $candidateTemplates = [
                isset($backendConfig['design']['templates'][$templateName]) ?
                    $backendConfig['design']['templates'][$templateName] : null,
                'harmony_admin/' . $templateName . '.html.twig',
            ];
            $templatePath       = $this->findFirstExistingTemplate($candidateTemplates) ?: $defaultTemplatePath;

            if (null === $templatePath) {
                throw new \RuntimeException(sprintf('None of the templates defined for the global "%s" template of the backend exists (templates defined: %s).',
                    $templateName, implode(', ', $candidateTemplates)));
            }

            $backendConfig['design']['templates'][$templateName] = $templatePath;
        }

        return $backendConfig;
    }

    /**
     * Determines the template used to render each backend element. This is not
     * trivial because templates can depend on the entity displayed and they
     * define an advanced override mechanism.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processFieldTemplates(array $backendConfig): array
    {
        foreach ($backendConfig['entities'] as $entityName => $entityConfig) {
            foreach (['list', 'show'] as $view) {
                foreach ($entityConfig[$view]['fields'] as $fieldName => $fieldMetadata) {
                    if (null !== $fieldMetadata['template']) {
                        continue;
                    }

                    // needed to add support for immutable datetime/date/time fields
                    // (which are rendered using the same templates as their non immutable counterparts)
                    if ('_immutable' === mb_substr($fieldMetadata['dataType'], - 10)) {
                        $fieldTemplateName = 'field_' . mb_substr($fieldMetadata['dataType'], 0, - 10);
                    } else {
                        $fieldTemplateName = 'field_' . $fieldMetadata['dataType'];
                    }

                    // primary key values are displayed unmodified to prevent common issues
                    // such as formatting its values as numbers (e.g. `1,234` instead of `1234`)
                    if ($entityConfig['primary_key_field_name'] === $fieldName) {
                        $template = $entityConfig['templates']['field_id'];
                    } elseif (array_key_exists($fieldTemplateName, $entityConfig['templates'])) {
                        $template = $entityConfig['templates'][$fieldTemplateName];
                    } else {
                        $template = $entityConfig['templates']['label_undefined'];
                    }

                    $entityConfig[$view]['fields'][$fieldName]['template'] = $template;
                }
            }

            $backendConfig['entities'][$entityName] = $entityConfig;
        }

        return $backendConfig;
    }

    /**
     * @param array $templatePaths
     *
     * @return mixed|null|string|string[]
     */
    private function findFirstExistingTemplate(array $templatePaths)
    {
        foreach ($templatePaths as $templatePath) {
            // template name normalization code taken from \Twig\Loader\FilesystemLoader::normalizeName()
            $templatePath = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $templatePath));
            $namespace    = FilesystemLoader::MAIN_NAMESPACE;

            if (isset($templatePath[0]) && '@' === $templatePath[0]) {
                if (false === $pos = strpos($templatePath, '/')) {
                    throw new \LogicException(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").',
                        $templatePath));
                }

                $namespace = substr($templatePath, 1, $pos - 1);
            }

            if (!isset($this->existingTemplates[$namespace])) {
                foreach ($this->twigLoader->getPaths($namespace) as $path) {
                    $finder = new Finder();
                    $finder->files()->in($path);

                    foreach ($finder as $templateFile) {
                        $template = $templateFile->getRelativePathname();

                        if ('\\' === DIRECTORY_SEPARATOR) {
                            $template = str_replace('\\', '/', $template);
                        }

                        if (FilesystemLoader::MAIN_NAMESPACE !== $namespace) {
                            $template = sprintf('@%s/%s', $namespace, $template);
                        }
                        $this->existingTemplates[$namespace][$template] = true;
                    }
                }
            }

            if (null !== $templatePath && isset($this->existingTemplates[$namespace][$templatePath])) {
                return $templatePath;
            }
        }
    }
}
