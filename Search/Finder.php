<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Search;

use Pagerfanta\Pagerfanta;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class Finder
{

    /** Constant */
    private const MAX_RESULTS = 15;

    /** @var QueryBuilder $queryBuilder */
    private $queryBuilder;

    /** @var Paginator $paginator */
    private $paginator;

    /**
     * Finder constructor.
     *
     * @param QueryBuilder $queryBuilder
     * @param Paginator    $paginator
     */
    public function __construct(QueryBuilder $queryBuilder, Paginator $paginator)
    {
        $this->queryBuilder = $queryBuilder;
        $this->paginator    = $paginator;
    }

    /**
     * @param array  $entityConfig
     * @param string $searchQuery
     * @param int    $page
     * @param int    $maxResults
     * @param string $sortField
     * @param string $sortDirection
     *
     * @return Pagerfanta
     */
    public function findByAllProperties(array $entityConfig, string $searchQuery, int $page = 1,
                                        int $maxResults = self::MAX_RESULTS, string $sortField = null,
                                        string $sortDirection = null)
    {
        $builder = $this->queryBuilder->createSearchQueryBuilder($entityConfig, $searchQuery, $sortField,
            $sortDirection);

        return $this->paginator->createPaginator($builder, $page, $maxResults);
    }
}
