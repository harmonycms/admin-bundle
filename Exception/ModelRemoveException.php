<?php

namespace Harmony\Bundle\AdminBundle\Exception;

use function sprintf;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ModelRemoveException extends BaseException
{

    /**
     * @param ExceptionContext $context
     */
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext('exception.model_remove',
            sprintf('There is a ForeignKeyConstraintViolationException for the Doctrine model associated with "%s". Solution: disable the "delete" action for this model or configure the "cascade={"remove"}" attribute for the related property in the Doctrine model. Full exception: %s',
                $parameters['model_name'], $parameters['message']), $parameters, 409);

        parent::__construct($exceptionContext);
    }
}
