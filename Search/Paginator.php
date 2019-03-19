<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Search;

use Doctrine\ODM\MongoDB\Query\Builder as DoctrineOdmQueryBuilder;
use Doctrine\ORM\Query as DoctrineQuery;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;
use Pagerfanta\Pagerfanta;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class Paginator
{

    private const MAX_ITEMS = 15;

    /**
     * Creates a Doctrine ORM paginator for the given query builder.
     *
     * @param DoctrineQuery|DoctrineQueryBuilder|DoctrineOdmQueryBuilder $builder
     * @param int                                                        $page
     * @param int                                                        $maxPerPage
     *
     * @return Pagerfanta
     */
    public function createPaginator($builder, $page = 1, $maxPerPage = self::MAX_ITEMS)
    {
        if ($builder instanceof DoctrineOdmQueryBuilder) {
            $adapter = new DoctrineODMMongoDBAdapter($builder);
        } else {
            $adapter = new DoctrineORMAdapter($builder, true, false);
        }

        $paginator = new Pagerfanta($adapter);
        $paginator->setMaxPerPage($maxPerPage);
        $paginator->setCurrentPage($page);

        return $paginator;
    }
}
