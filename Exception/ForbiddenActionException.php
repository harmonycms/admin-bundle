<?php

declare(strict_types=1);

namespace Harmony\Bundle\AdminBundle\Exception;

use function sprintf;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class ForbiddenActionException extends BaseException
{

    /**
     * @param ExceptionContext $context
     */
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext(
            'exception.forbidden_action',
            sprintf('The requested "%s" action is not allowed for the "%s" model. Solution: remove the "%s" action from the "disabled_actions" option, which can be configured globally for the entire backend or locally for the "%s" model.', $parameters['action'], $parameters['model_name'], $parameters['action'], $parameters['model_name']),
            $parameters,
            403
        );

        parent::__construct($exceptionContext);
    }
}
