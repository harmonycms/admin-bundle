<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Form\Type\Configurator;

use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Symfony\Component\Form\FormConfigInterface;
use function array_key_exists;

/**
 * @author Konstantin Grachev <me@grachevko.ru>
 */
final class TypeConfigurator implements TypeConfiguratorInterface
{

    /**
     * @var ConfigManager
     */
    private $configManager;

    /**
     * TypeConfigurator constructor.
     *
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * {@inheritdoc}
     */
    public function configure($name, array $options, array $metadata, FormConfigInterface $parentConfig)
    {
        if (!array_key_exists('label', $options) && array_key_exists('label', $metadata)) {
            $options['label'] = $metadata['label'];
        }

        if (empty($options['translation_domain'])) {
            $modelConfig = $this->configManager->getModelConfig($parentConfig->getOption('model'));

            if (!empty($modelConfig['translation_domain'])) {
                $options['translation_domain'] = $modelConfig['translation_domain'];
            }
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($type, array $options, array $metadata)
    {
        return true;
    }
}
