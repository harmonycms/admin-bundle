<?php

namespace Harmony\Bundle\AdminBundle\Search;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\MongoDB\Query\Builder as DoctrineOdmQueryBuilder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder as DoctrineOrmQueryBuilder;

/**
 * Class DoctrineBuilderRegistry
 *
 * @package Harmony\Bundle\AdminBundle\Search
 */
class DoctrineBuilderRegistry
{

    /** @var ManagerRegistry $registry */
    private $registry;

    /**
     * QueryBuilder constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Creates the builder used to get all the records displayed by the "list" view.
     *
     * @param string      $class
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @param string|null $dqlFilter
     *
     * @return DoctrineOrmQueryBuilder|DoctrineOdmQueryBuilder
     */
    public function createListBuilder(string $class, string $sortField = null, string $sortDirection = null,
                                      string $dqlFilter = null)
    {
        /* @var ObjectManager|DocumentManager|EntityManager $objectManager */
        $objectManager = $this->registry->getManagerForClass($class);

        if ($objectManager instanceof DocumentManager) {
            return DoctrineOdmBuilder::createListBuilder($objectManager, $class, $sortField, $sortDirection);
        }

        return DoctrineOrmBuilder::createListBuilder($objectManager, $class, $sortField, $sortDirection, $dqlFilter);
    }

    /**
     * Creates the builder used to get the results of the search query performed by the user in the "search" view.
     *
     * @param array       $config
     * @param string      $searchQuery
     * @param string|null $sortField
     * @param string|null $sortDirection
     * @param string|null $dqlFilter
     *
     * @return DoctrineOrmQueryBuilder|DoctrineOdmQueryBuilder
     * @throws \MongoException
     */
    public function createSearchBuilder(array $config, string $searchQuery, string $sortField = null,
                                        string $sortDirection = null, string $dqlFilter = null)
    {
        /* @var ObjectManager|DocumentManager|EntityManager $objectManager */
        $objectManager = $this->registry->getManagerForClass($config['class']);

        if ($objectManager instanceof DocumentManager) {
            return DoctrineOdmBuilder::createSearchBuilder($objectManager, $config, $searchQuery, $sortField,
                $sortDirection);
        }

        return DoctrineOrmBuilder::createSearchBuilder($objectManager, $config, $searchQuery, $sortField,
            $sortDirection, $dqlFilter);
    }
}