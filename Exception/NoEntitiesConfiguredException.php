<?php

namespace Harmony\Bundle\AdminBundle\Exception;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class NoEntitiesConfiguredException extends BaseException
{

    /**
     * @param ExceptionContext $context
     */
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext('exception.no_entities_configured',
            'The backend is empty because you haven\'t configured any Doctrine model to manage. Solution: edit your configuration file (e.g. "config/packages/harmony_admin.yaml") and configure the backend under the "harmony_admin" key.',
            $parameters, 500);

        parent::__construct($exceptionContext);
    }
}
