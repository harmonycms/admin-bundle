<?php

namespace Harmony\Bundle\AdminBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Harmony\Bundle\AdminBundle\Form\Type\ParameterType;
use Harmony\Bundle\CoreBundle\Model\ParameterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class SettingsController
 * @Route("/settings/parameters", name="admin_settings_parameters_")
 *
 * @package Harmony\Bundle\AdminBundle\Controller
 */
class ParametersController extends AbstractController
{

    /** @var ManagerRegistry $registry */
    protected $registry;

    /** @var ParameterBagInterface $parameterBag */
    protected $parameterBag;

    /** @var TranslatorInterface $translator */
    protected $translator;

    /**
     * SettingsController constructor.
     *
     * @param ManagerRegistry       $registry
     * @param ParameterBagInterface $parameterBag
     * @param TranslatorInterface   $translator
     */
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag,
                                TranslatorInterface $translator)
    {
        $this->registry     = $registry;
        $this->parameterBag = $parameterBag;
        $this->translator   = $translator;
    }

    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('@HarmonyAdmin\settings\parameters.html.twig', [
            'parameters'    => $this->registry->getRepository(ParameterInterface::class)->findAll(),
            'parameter_bag' => $this->parameterBag
        ]);
    }

    /**
     * @Route("/add", name="add")
     * @param Request $request
     *
     * @return Response
     */
    public function add(Request $request): Response
    {
        $form = $this->createForm(ParameterType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->registry->getManager()->persist($form->getData());
            $this->registry->getManager()->flush();

            $this->addFlash('success',
                $this->translator->trans('settings.parameter.add.success', [], 'HarmonyAdminBundle'));

            return $this->redirectToRoute('admin_settings_parameters_index');
        }

        return $this->render('@HarmonyAdmin\settings\parameter_add.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @param Request    $request
     * @param int|string $id
     *
     * @return Response
     */
    public function edit(Request $request, $id): Response
    {
        $parameter = $this->registry->getRepository(ParameterInterface::class)->find($id);

        $form = $this->createForm(ParameterType::class, $parameter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->registry->getManager()->flush();

            $this->addFlash('success',
                $this->translator->trans('settings.parameter.edit.success', [], 'HarmonyAdminBundle'));

            return $this->redirectToRoute('admin_settings_parameters_index');
        }

        return $this->render('@HarmonyAdmin\settings\parameter_edit.html.twig', [
            'parameter' => $parameter,
            'form'      => $form->createView()
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param Request    $request
     * @param string|int $id
     *
     * @return Response
     */
    public function delete(Request $request, $id): Response
    {
        $parameter = $this->registry->getRepository(ParameterInterface::class)->find($id);

        $form = $this->createFormBuilder($parameter, [
            'action' => $this->generateUrl('admin_settings_parameters_delete', ['id' => $id])
        ])->add('submit', SubmitType::class)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->registry->getManager()->remove($parameter);
            $this->registry->getManager()->flush();

            $this->addFlash('success',
                $this->translator->trans('settings.parameter.delete.success', [], 'HarmonyAdminBundle'));

            return $this->redirectToRoute('admin_settings_parameters_index');
        }

        return $this->render('@HarmonyAdmin\settings\parameter_delete.html.twig', [
            'parameter' => $parameter,
            'form'      => $form->createView()
        ]);
    }
}