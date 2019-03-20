<?php

namespace Harmony\Bundle\AdminBundle\Search;

use Doctrine\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoRegex;
use function ctype_digit;
use function explode;
use function in_array;
use function is_int;
use function is_numeric;
use function mb_strtolower;

/**
 * Class DoctrineOdmBuilder
 *
 * @package Harmony\Bundle\AdminBundle\Search
 */
class DoctrineOdmBuilder
{

    /**
     * Creates the builder used to get all the records displayed by the "list" view.
     *
     * @param DocumentManager $manager
     * @param string          $class
     * @param string|null     $sortField
     * @param string|null     $sortDirection
     *
     * @return Builder
     */
    public static function createListBuilder(DocumentManager $manager, string $class, ?string $sortField,
                                             ?string $sortDirection): Builder
    {
        /** @var Builder $queryBuilder */
        $queryBuilder = $manager->createQueryBuilder($class);

        if (null !== $sortField) {
            $queryBuilder->sort($sortField, $sortDirection);
        }

        return $queryBuilder;
    }

    /**
     * Creates the builder used to get the results of the search query performed by the user in the "search" view.
     *
     * @param DocumentManager $manager
     * @param array           $config
     * @param string          $searchQuery
     * @param string|null     $sortField
     * @param string|null     $sortDirection
     *
     * @return Builder
     * @throws \MongoException
     */
    public static function createSearchBuilder(DocumentManager $manager, array $config, string $searchQuery,
                                               ?string $sortField, ?string $sortDirection): Builder
    {
        /** @var Builder $queryBuilder */
        $queryBuilder = $manager->createQueryBuilder($config['class']);

        $isSearchQueryNumeric      = is_numeric($searchQuery);
        $isSearchQuerySmallInteger = (is_int($searchQuery) || ctype_digit($searchQuery)) && $searchQuery >= - 32768 &&
            $searchQuery <= 32767;
        $isSearchQueryInteger      = (is_int($searchQuery) || ctype_digit($searchQuery)) &&
            $searchQuery >= - 2147483648 && $searchQuery <= 2147483647;
        $isSearchQueryUuid         = 1 ===
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $searchQuery);
        $lowerSearchQuery          = mb_strtolower($searchQuery);

        // NO_ASSOCIATION $documentAlreadyJoined = array();
        foreach ($config['search']['fields'] as $fieldName => $metadata) {
            $isSmallIntegerField = 'smallint' === $metadata['dataType'];
            $isIntegerField      = 'integer' === $metadata['dataType'];
            $isNumericField      = in_array($metadata['dataType'], ['number', 'bigint', 'decimal', 'float']);
            $isTextField         = in_array($metadata['dataType'], ['string', 'text']);
            $isGuidField         = 'guid' === $metadata['dataType'];
            if ($isSmallIntegerField && $isSearchQuerySmallInteger || $isIntegerField && $isSearchQueryInteger ||
                $isNumericField && $isSearchQueryNumeric) {
                // adding '0' turns the string into a numeric value
                $queryBuilder->addOr($queryBuilder->expr()->field($fieldName)->equals(0 + $searchQuery));
            } elseif ($isGuidField && $isSearchQueryUuid) {
                $queryBuilder->addOr($queryBuilder->expr()->field($fieldName)->equals($searchQuery));
            } elseif ($isTextField) {
                // Fuzzy query
                $fuzzyRegexp = new MongoRegex('/.*' . $lowerSearchQuery . '.*/i');
                $queryBuilder->addOr($queryBuilder->expr()->field($fieldName)->operator('$regex', $fuzzyRegexp));
                // Words query
                $queryBuilder->addOr($queryBuilder->expr()->field($fieldName)->in(explode(' ', $lowerSearchQuery)));
            }
        }

        if (null !== $sortField) {
            $queryBuilder->sort($sortField, $sortDirection ?: 'DESC');
        }

        return $queryBuilder;
    }
}