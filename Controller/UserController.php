<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Harmony\Bundle\AdminBundle\Form\Type\ProfileType;
use Harmony\Bundle\UserBundle\Model\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserController
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class UserController extends AbstractController
{

    /** @var UserManager $userManager */
    protected $userManager;

    /**
     * UserController constructor.
     *
     * @param UserManager $userManager
     */
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * @Route("/profile", name="admin_profile")
     * @param Request $request
     *
     * @return Response
     */
    public function profile(Request $request): Response
    {
        $form = $this->createForm(ProfileType::class, $this->getUser());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $this->userManager->updateUser($user);

            $this->addFlash('success', 'flash.profile_success');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('@HarmonyAdmin/default/profile.html.twig', [
            'user' => $this->getUser(),
            'form' => $form->createView()
        ]);
    }
}