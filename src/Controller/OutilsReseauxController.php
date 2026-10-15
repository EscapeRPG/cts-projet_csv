<?php

namespace App\Controller;

use App\Entity\OutilsReseaux;
use App\Form\OutilsReseauxType;
use App\Repository\OutilsReseauxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_TOOLS_VIEW")]
final class OutilsReseauxController extends AbstractController
{
    #[Route('/outils/list', name: 'app_outils_list')]
    public function index(OutilsReseauxRepository $repository): Response
    {
        $outils = $repository->findAll();

        return $this->render('outils_reseaux/list.html.twig', [
            'outils' => $outils,
        ]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/outils/add', name: 'app_outils_add')]
    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $form = $this->createForm(OutilsReseauxType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $outils = $form->getData();
            $logo = $form->get('logo')->getData();

            if ($logo instanceof UploadedFile) {
                $filename = bin2hex(random_bytes(16)) . '.' . $logo->guessExtension();

                try {
                    $logo->move($this->getParameter('kernel.project_dir') . '/public/img/outils-reseaux', $filename);
                    $outils->setLogo('outils-reseaux/' . $filename);
                } catch (FileException $exception) {
                    $form->get('logo')->addError(new FormError('Impossible d’enregistrer le logo. Veuillez réessayer.'));

                    return $this->render('outils_reseaux/add.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
            }

            $entityManager->persist($outils);
            $entityManager->flush();

            $this->addFlash('success', 'Outil ajouté avec succès.');

            return $this->redirectToRoute('app_outils_list');
        }

        return $this->render('outils_reseaux/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws RandomException
     */
    #[Route('/outils/edit/{id}', name: 'app_outils_edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        OutilsReseaux $outils
    ): Response
    {
        $form = $this->createForm(OutilsReseauxType::class, $outils);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $outils = $form->getData();
            $logo = $form->get('logo')->getData();

            if ($logo instanceof UploadedFile) {
                $filename = bin2hex(random_bytes(16)) . '.' . $logo->guessExtension();

                try {
                    $logo->move($this->getParameter('kernel.project_dir') . '/public/img/outils-reseaux', $filename);
                    $outils->setLogo('outils-reseaux/' . $filename);
                } catch (FileException $exception) {
                    $form->get('logo')->addError(new FormError('Impossible d’enregistrer le logo. Veuillez réessayer.'));

                    return $this->render('outils_reseaux/edit.html.twig', [
                        'form' => $form->createView(),
                        'outils' => $outils,
                    ]);
                }
            }

            $entityManager->persist($outils);
            $entityManager->flush();

            $this->addFlash('success', 'Outil modifié avec succès.');

            return $this->redirectToRoute('app_outils_list');
        }

        return $this->render('outils_reseaux/edit.html.twig', [
            'outils' => $outils,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/outils/delete/{id}', name: 'app_outils_delete')]
    public function delete(
        OutilsReseaux $outils,
        EntityManagerInterface $entityManager
    ): Response
    {
        $entityManager->remove($outils);
        $entityManager->flush();

        $this->addFlash('success', 'Outil supprimé avec succès.');

        return $this->redirectToRoute('app_outils_list');
    }
}
