<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Configuration;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use function property_exists;

/**
 * Introspects the metadata of the Doctrine models to complete the
 * configuration of the properties.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class MetadataConfigPass implements ConfigPassInterface
{

    /** @var ManagerRegistry $registry */
    private $registry;

    /**
     * MetadataConfigPass constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array $backendConfig
     *
     * @return array
     */
    public function process(array $backendConfig)
    {
        foreach ($backendConfig['models'] as $modelName => $modelConfig) {
            try {
                $modelConfig['class'] = $this->registry->getManager()->getClassMetadata($modelConfig['class'])->getName();
                $objectManager        = $this->registry->getManagerForClass($modelConfig['class']);
            }
            catch (\ReflectionException $e) {
                throw new InvalidTypeException(sprintf('The configured class "%s" for the path "harmony_admin.models.%s" does not exist. Did you forget to create the model class or to define its namespace?',
                    $modelConfig['class'], $modelName));
            }

            if (null === $objectManager) {
                throw new InvalidTypeException(sprintf('The configured class "%s" for the path "harmony_admin.models.%s" is no mapped model.',
                    $modelConfig['class'], $modelName));
            }
            /** @var ClassMetadata $classMetadata */
            $classMetadata = $objectManager->getMetadataFactory()->getMetadataFor($modelConfig['class']);

            if (!isset($classMetadata->getIdentifierFieldNames()[0])) {
                throw new \RuntimeException('No ID defined for model ' . $modelConfig['class']);
            }

            $modelConfig['primary_key_field_name'] = $classMetadata->getIdentifierFieldNames()[0];

            $modelConfig['properties'] = $this->processModelPropertiesMetadata($classMetadata);

            $backendConfig['models'][$modelName] = $modelConfig;
        }

        return $backendConfig;
    }

    /**
     * Takes the class metadata introspected via Doctrine and completes its
     * contents to simplify data processing for the rest of the application.
     *
     * @param ClassMetadata $classMetadata The class metadata introspected via Doctrine
     *
     * @return array The properties metadata provided by Doctrine
     * @throws \RuntimeException
     */
    private function processModelPropertiesMetadata(ClassMetadata $classMetadata)
    {
        $PropertiesMetadata = [];

        if (property_exists($classMetadata, 'isIdentifierComposite') && $classMetadata->isIdentifierComposite) {
            throw new \RuntimeException(sprintf("The '%s' model isn't valid because it contains a composite primary key.",
                $classMetadata->name));
        }

        // introspect regular fields
        foreach ($classMetadata->fieldMappings as $fieldName => $fieldMetadata) {
            $PropertiesMetadata[$fieldName] = $fieldMetadata;
        }

        // introspect fields for associations
        foreach ($classMetadata->associationMappings as $fieldName => $associationMetadata) {
            $PropertiesMetadata[$fieldName] = array_merge($associationMetadata, [
                'type'            => 'association',
                'associationType' => $associationMetadata['type'],
            ]);

            // associations different from *-to-one cannot be sorted
            if ($associationMetadata['type'] & 12 || 'many' === $associationMetadata['type']) {
                $PropertiesMetadata[$fieldName]['sortable'] = false;
            }
        }

        return $PropertiesMetadata;
    }
}
