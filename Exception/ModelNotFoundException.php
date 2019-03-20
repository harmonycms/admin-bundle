<?php

namespace Harmony\Bundle\AdminBundle\Exception;

use function sprintf;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ModelNotFoundException extends BaseException
{

    /**
     * @param ExceptionContext $context
     */
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext('exception.model_not_found',
            sprintf('The "%s" model with "%s = %s" does not exist in the database. The model may have been deleted by mistake or by a "cascade={"remove"}" operation executed by Doctrine.',
                $parameters['model_name'], $parameters['model_id_name'], $parameters['model_id_value']), $parameters,
            404);

        parent::__construct($exceptionContext);
    }
}
