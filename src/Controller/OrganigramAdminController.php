<?php

namespace App\Controller;

use App\Form\Type\OrganigramUploadType;
use App\Service\Organigram\OrganigramConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class OrganigramAdminController extends AbstractController
{

    /**
     * Displays structural organigram.
     */
    #[IsGranted('ROLE_ORGANIGRAM_STRUCT_VIEW')]
    #[Route("/organigramme", name: 'app_organigram')]
    public function organigram(\App\Service\Organigram\OrganigramConfig $config): Response
    {
        $mapping = $config->getMapping();
        $mtime = $config->getPdfMtime();
        $pdfUrl = $config->hasPdf() ? $this->generateUrl('app_organigram_pdf', $mtime ? ['v' => $mtime] : []) : null;

        return $this->render('organigram/organigram.html.twig', [
            'organigramKey' => 'structurel',
            'pdfUrl' => $pdfUrl,
            'page' => $mapping['structurel'] ?? null,
        ]);
    }

    /**
     * Displays properties organigram.
     */
    #[IsGranted('ROLE_ORGANIGRAM_IMMO_VIEW')]
    #[Route("/organigramme-immobilier", name: 'app_organigram_immobilier')]
    public function organigramProperty(\App\Service\Organigram\OrganigramConfig $config): Response
    {
        $mapping = $config->getMapping();
        $mtime = $config->getPdfMtime();
        $pdfUrl = $config->hasPdf() ? $this->generateUrl('app_organigram_pdf', $mtime ? ['v' => $mtime] : []) : null;

        return $this->render('organigram/organigram.html.twig', [
            'organigramKey' => 'immobilier',
            'pdfUrl' => $pdfUrl,
            'page' => $mapping['immobilier'] ?? null,
        ]);
    }

    /**
     * Displays hierarchy organigram.
     */
    #[IsGranted('ROLE_ORGANIGRAM_HIERARCHY_VIEW')]
    #[Route("/organigramme-hierarchique", name: 'app_organigram_hierarchique')]
    public function organigramHierarchy(\App\Service\Organigram\OrganigramConfig $config): Response
    {
        $mapping = $config->getMapping();
        $mtime = $config->getPdfMtime();
        $pdfUrl = $config->hasPdf() ? $this->generateUrl('app_organigram_pdf', $mtime ? ['v' => $mtime] : []) : null;

        return $this->render('organigram/organigram.html.twig', [
            'organigramKey' => 'hierarchique',
            'pdfUrl' => $pdfUrl,
            'page' => $mapping['hierarchique'] ?? null,
        ]);
    }

    #[IsGranted('ROLE_ORGANIGRAM_VIEW')]
    #[Route('/organigramme/pdf', name: 'app_organigram_pdf', methods: ['GET'])]
    public function pdf(OrganigramConfig $config): Response
    {
        $path = $config->getPdfPath();
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'organigramme.pdf');
        // Keep it simple: avoid stale caches when admins replace the file.
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
        return $response;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/organigramme/editer', name: 'app_organigram_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OrganigramConfig $config): Response
    {
        $form = $this->createForm(OrganigramUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $file */
            $file = $form->get('pdf')->getData();
            if ($file) {
                $config->ensureBaseDir();
                $file->move(\dirname($config->getPdfPath()), \basename($config->getPdfPath()));
            }

            $this->addFlash('success', 'PDF mis a jour.');
            return $this->redirectToRoute('app_organigram_configure');
        }

        return $this->render('organigram/edit.html.twig', [
            'form' => $form->createView(),
            'hasPdf' => $config->hasPdf(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/organigramme/configurer', name: 'app_organigram_configure', methods: ['GET'])]
    public function configure(OrganigramConfig $config): Response
    {
        $mapping = $config->getMapping();
        $mtime = $config->getPdfMtime();
        $pdfUrl = $config->hasPdf() ? $this->generateUrl('app_organigram_pdf', $mtime ? ['v' => $mtime] : []) : null;

        return $this->render('organigram/configure.html.twig', [
            'pdfUrl' => $pdfUrl,
            'mapping' => $mapping,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/organigramme/configurer', name: 'app_organigram_configure_save', methods: ['POST'])]
    public function configureSave(Request $request, OrganigramConfig $config): Response
    {
        if (!$this->isCsrfTokenValid('organigram_configure', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $toInt = static function (mixed $v): ?int {
            if (is_int($v) && $v > 0) return $v;
            if (is_string($v) && ctype_digit($v) && (int) $v > 0) return (int) $v;
            return null;
        };

        $config->saveMapping([
            'structurel' => $toInt($request->request->get('structurel')),
            'immobilier' => $toInt($request->request->get('immobilier')),
            'hierarchique' => $toInt($request->request->get('hierarchique')),
        ]);

        $this->addFlash('success', 'Configuration mise a jour.');
        return $this->redirectToRoute('app_organigram');
    }
}
