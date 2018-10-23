<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use FOS\UserBundle\Doctrine\UserManager;
use Harmony\Bundle\AdminBundle\Form\Type\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
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
     * @param Request $request
     *
     * @return Response
     */
    public function profile(Request $request): Response
    {
        $form = $this->createForm(UserType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user          = $form->getData();
            $entityManager = $this->getDoctrine()->getManager();
            /** @var UserManager $userManager */
            $userManager = $this->get('fos_user.user_manager');
            $userManager->updatePassword($user);
            $entityManager->flush();

            $this->addFlash('success', 'flash.profile_success');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('@HarmonyAdmin/default/profile.html.twig', [
            'user' => $this->getUser(),
            'form' => $form->createView()
        ]);
    }
}