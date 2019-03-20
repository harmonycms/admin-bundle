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

    /** @var DoctrineBuilderRegistry $builderRegistry */
    private $builderRegistry;

    /** @var Paginator $paginator */
    private $paginator;

    /**
     * Finder constructor.
     *
     * @param DoctrineBuilderRegistry $builderRegistry
     * @param Paginator               $paginator
     */
    public function __construct(DoctrineBuilderRegistry $builderRegistry, Paginator $paginator)
    {
        $this->builderRegistry = $builderRegistry;
        $this->paginator       = $paginator;
    }

    /**
     * @param array  $modelConfig
     * @param string $searchQuery
     * @param int    $page
     * @param int    $maxResults
     * @param string $sortField
     * @param string $sortDirection
     *
     * @return Pagerfanta
     * @throws \MongoException
     */
    public function findByAllProperties(array $modelConfig, string $searchQuery, int $page = 1,
                                        int $maxResults = self::MAX_RESULTS, string $sortField = null,
                                        string $sortDirection = null)
    {
        $builder = $this->builderRegistry->createSearchBuilder($modelConfig, $searchQuery, $sortField, $sortDirection);

        return $this->paginator->createPaginator($builder, $page, $maxResults);
    }
}
