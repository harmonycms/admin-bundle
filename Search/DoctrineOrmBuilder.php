<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Search;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use function array_key_exists;
use function count;
use function ctype_digit;
use function explode;
use function is_int;
use function is_numeric;
use function mb_strtolower;
use function sprintf;
use function strpos;

/**
 * Class DoctrineOrmBuilder
 *
 * @package Harmony\Bundle\AdminBundle\Search
 */
class DoctrineOrmBuilder
{

    /**
     * Creates the builder used to get all the records displayed by the "list" view.
     *
     * @param EntityManager $manager
     * @param string        $class
     * @param string|null   $sortField
     * @param string|null   $sortDirection
     * @param string|null   $dqlFilter
     *
     * @return QueryBuilder
     */
    public static function createListBuilder(EntityManager $manager, string $class, ?string $sortField,
                                             ?string $sortDirection, ?string $dqlFilter): QueryBuilder
    {
        /* @var ClassMetadata $classMetadata */
        $classMetadata = $manager->getClassMetadata($class);

        /* @var QueryBuilder $queryBuilder */
        $queryBuilder = $manager->createQueryBuilder()->select('entity')->from($class, 'entity');

        $isSortedByDoctrineAssociation = self::isDoctrineAssociation($classMetadata, $sortField);
        if ($isSortedByDoctrineAssociation) {
            $sortFieldParts = explode('.', $sortField);
            $queryBuilder->leftJoin('entity.' . $sortFieldParts[0], $sortFieldParts[0]);
        }

        if (!empty($dqlFilter)) {
            $queryBuilder->andWhere($dqlFilter);
        }

        if (null !== $sortField) {
            $queryBuilder->orderBy(sprintf('%s%s', $isSortedByDoctrineAssociation ? '' : 'entity.', $sortField),
                $sortDirection);
        }

        return $queryBuilder;
    }

    /**
     * Creates the builder used to get the results of the search query performed by the user in the "search" view.
     *
     * @param EntityManager $manager
     * @param array         $config
     * @param string        $searchQuery
     * @param string|null   $sortField
     * @param string|null   $sortDirection
     * @param string|null   $dqlFilter
     *
     * @return QueryBuilder
     */
    public static function createSearchBuilder(EntityManager $manager, array $config, string $searchQuery,
                                               ?string $sortField, ?string $sortDirection,
                                               ?string $dqlFilter): QueryBuilder
    {
        /* @var ClassMetadata $classMetadata */
        $classMetadata = $manager->getClassMetadata($config['class']);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $manager->createQueryBuilder()->select('entity');

        $isSearchQueryNumeric      = is_numeric($searchQuery);
        $isSearchQuerySmallInteger = (is_int($searchQuery) || ctype_digit($searchQuery)) && $searchQuery >= - 32768 &&
            $searchQuery <= 32767;
        $isSearchQueryInteger      = (is_int($searchQuery) || ctype_digit($searchQuery)) &&
            $searchQuery >= - 2147483648 && $searchQuery <= 2147483647;
        $isSearchQueryUuid         = 1 ===
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $searchQuery);
        $lowerSearchQuery          = mb_strtolower($searchQuery);

        $queryParameters       = [];
        $entitiesAlreadyJoined = [];
        foreach ($config['search']['fields'] as $fieldName => $metadata) {
            $entityName = 'entity';
            if (self::isDoctrineAssociation($classMetadata, $fieldName)) {
                [$associatedEntityName, $associatedFieldName] = explode('.', $fieldName);
                if (!in_array($associatedEntityName, $entitiesAlreadyJoined)) {
                    $queryBuilder->leftJoin('entity.' . $associatedEntityName, $associatedEntityName);
                    $entitiesAlreadyJoined[] = $associatedEntityName;
                }

                $entityName = $associatedEntityName;
                $fieldName  = $associatedFieldName;
            }

            $isSmallIntegerField = 'smallint' === $metadata['dataType'];
            $isIntegerField      = 'integer' === $metadata['dataType'];
            $isNumericField      = in_array($metadata['dataType'], ['number', 'bigint', 'decimal', 'float']);
            $isTextField         = in_array($metadata['dataType'], ['string', 'text']);
            $isGuidField         = 'guid' === $metadata['dataType'];

            // this complex condition is needed to avoid issues on PostgreSQL databases
            if (($isSmallIntegerField && $isSearchQuerySmallInteger) || ($isIntegerField && $isSearchQueryInteger) ||
                ($isNumericField && $isSearchQueryNumeric)) {
                $queryBuilder->orWhere(sprintf('%s.%s = :numeric_query', $entityName, $fieldName));
                // adding '0' turns the string into a numeric value
                $queryParameters['numeric_query'] = 0 + $searchQuery;
            } elseif ($isGuidField && $isSearchQueryUuid) {
                $queryBuilder->orWhere(sprintf('%s.%s = :uuid_query', $entityName, $fieldName));
                $queryParameters['uuid_query'] = $searchQuery;
            } elseif ($isTextField) {
                $queryBuilder->orWhere(sprintf('LOWER(%s.%s) LIKE :fuzzy_query', $entityName, $fieldName));
                $queryParameters['fuzzy_query'] = '%' . $lowerSearchQuery . '%';

                $queryBuilder->orWhere(sprintf('LOWER(%s.%s) IN (:words_query)', $entityName, $fieldName));
                $queryParameters['words_query'] = explode(' ', $lowerSearchQuery);
            }
        }

        if (0 !== count($queryParameters)) {
            $queryBuilder->setParameters($queryParameters);
        }

        if (!empty($dqlFilter)) {
            $queryBuilder->andWhere($dqlFilter);
        }

        $isSortedByDoctrineAssociation = self::isDoctrineAssociation($classMetadata, $sortField);
        if ($isSortedByDoctrineAssociation) {
            $associatedEntityName = explode('.', $sortField)[0];
            if (!in_array($associatedEntityName, $entitiesAlreadyJoined)) {
                $queryBuilder->leftJoin('entity.' . $associatedEntityName, $associatedEntityName);
                $entitiesAlreadyJoined[] = $associatedEntityName;
            }
        }

        if (null !== $sortField) {
            $queryBuilder->orderBy(sprintf('%s%s', $isSortedByDoctrineAssociation ? '' : 'entity.', $sortField),
                $sortDirection ?: 'DESC');
        }

        return $queryBuilder;
    }

    /**
     * Checking if the field name contains a '.' is not enough to decide if it's a
     * Doctrine association. This also happens when using embedded classes, so the
     * embeddedClasses property from Doctrine class metadata must be checked too.
     *
     * @param ClassMetadata $classMetadata
     * @param string|null   $fieldName
     *
     * @return bool
     */
    protected static function isDoctrineAssociation(ClassMetadata $classMetadata, $fieldName)
    {
        if (null === $fieldName) {
            return false;
        }

        $fieldNameParts = explode('.', $fieldName);

        return false !== strpos($fieldName, '.') &&
            !array_key_exists($fieldNameParts[0], $classMetadata->embeddedClasses);
    }
}