<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class UserController extends Controller
{

    /**
     * @Route("/profile", name="admin_profile")
     * @return Response
     */
    public function profile(): Response
    {
        return $this->render('@HarmonyAdmin/default/profile.html.twig');
    }
}