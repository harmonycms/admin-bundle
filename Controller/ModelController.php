<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ModelController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class ModelController extends AbstractController
{

    /**
     * @Route("/model/{model}/{action}", name="admin_model")
     * @param Request $request
     * @param string  $model
     * @param string  $action
     *
     * @return Response
     */
    public function index(Request $request, string $model, string $action = 'list'): Response
    {

    }
}