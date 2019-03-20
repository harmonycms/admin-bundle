<?php

namespace Harmony\Bundle\AdminBundle\Exception;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ModelRemoveException extends BaseException
{
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext(
            'exception.entity_remove',
            sprintf('There is a ForeignKeyConstraintViolationException for the Doctrine entity associated with "%s". Solution: disable the "delete" action for this entity or configure the "cascade={"remove"}" attribute for the related property in the Doctrine entity. Full exception: %s', $parameters['entity_name'], $parameters['message']),
            $parameters,
            409
        );

        parent::__construct($exceptionContext);
    }
}
