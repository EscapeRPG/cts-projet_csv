<?php

namespace App\Controller;

use App\Form\Type\OrganigramUploadType;
use App\Service\Organigram\OrganigramConfig;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class OrganigramController extends AbstractController
{
    private const string SESSION_IMPORT = 'organigram_import';
    private const array VIEW_ROLES = [
        'structurel' => 'ROLE_ORGANIGRAM_STRUCT_VIEW',
        'immobilier' => 'ROLE_ORGANIGRAM_IMMO_VIEW',
        'hierarchique' => 'ROLE_ORGANIGRAM_HIERARCHY_VIEW',
    ];

    #[IsGranted('ROLE_ORGANIGRAM_VIEW')]
    #[Route('/organigramme', name: 'app_organigram')]
    public function organigram(): Response
    {
        if ($this->isGranted('ROLE_ORGANIGRAM_STRUCT_VIEW')) {
            return $this->redirectToRoute('app_organigram_structurel');
        } elseif ($this->isGranted('ROLE_ORGANIGRAM_IMMO_VIEW')) {
            return $this->redirectToRoute('app_organigram_immobilier');
        } else {
            return $this->redirectToRoute('app_organigram_hierarchique');
        }
    }

    #[IsGranted('ROLE_ORGANIGRAM_STRUCT_VIEW')]
    #[Route('/organigramme-structurel', name: 'app_organigram_structurel')]
    public function organigramStructural(OrganigramConfig $config): Response
    {
        return $this->renderOrganigram($config, 'structurel');
    }

    #[IsGranted('ROLE_ORGANIGRAM_IMMO_VIEW')]
    #[Route('/organigramme-immobilier', name: 'app_organigram_immobilier')]
    public function organigramProperty(OrganigramConfig $config): Response
    {
        return $this->renderOrganigram($config, 'immobilier');
    }

    #[IsGranted('ROLE_ORGANIGRAM_HIERARCHY_VIEW')]
    #[Route('/organigramme-hierarchique', name: 'app_organigram_hierarchique')]
    public function organigramHierarchy(OrganigramConfig $config): Response
    {
        return $this->renderOrganigram($config, 'hierarchique');
    }

    private function renderOrganigram(OrganigramConfig $config, string $key): Response
    {
        $path = $config->getDocumentPath($key);
        return $this->render('organigram/organigram.html.twig', [
            'organigramKey' => $key,
            'pdfUrl' => $path !== null ? $this->generateUrl('app_organigram_pdf', ['key' => $key]) : null,
            'downloadUrl' => $path !== null ? $this->generateUrl('app_organigram_download', ['key' => $key]) : null,
            'page' => $path !== null ? 1 : null,
        ]);
    }

    #[Route('/organigramme/pdf/{key}', name: 'app_organigram_pdf', requirements: ['key' => 'structurel|immobilier|hierarchique'], methods: ['GET'])]
    public function pdf(string $key, OrganigramConfig $config): Response
    {
        return $this->documentResponse($key, $config, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/organigramme/telecharger/{key}', name: 'app_organigram_download', requirements: ['key' => 'structurel|immobilier|hierarchique'], methods: ['GET'])]
    public function download(string $key, OrganigramConfig $config): Response
    {
        return $this->documentResponse($key, $config, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    private function documentResponse(string $key, OrganigramConfig $config, string $disposition): Response
    {
        $this->denyAccessUnlessGranted(self::VIEW_ROLES[$key]);
        $path = $config->getDocumentPath($key);
        if ($path === null || !is_file($path)) throw $this->createNotFoundException();

        return $this->pdfResponse($path, $key . '.pdf', $disposition);
    }

    private function pdfResponse(string $path, string $name, string $disposition): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition($disposition, $name);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        return $response;
    }

    #[IsGranted('ROLE_ORGANIGRAM_EDIT')]
    #[Route('/organigramme/editer', name: 'app_organigram_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OrganigramConfig $config, LoggerInterface $logger): Response
    {
        $form = $this->createForm(OrganigramUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $id = $config->stageImport($form->get('pdf')->getData());
                $previous = $request->getSession()->get(self::SESSION_IMPORT);
                $request->getSession()->set(self::SESSION_IMPORT, $id);
                if (is_string($previous)) $config->discardImport($previous);
                $this->addFlash('success', 'PDF importé. Associez les pages aux organigrammes à mettre à jour.');
                return $this->redirectToRoute('app_organigram_configure');
            } catch (\Exception $exception) {
                $logger->error('Échec de l’import du PDF des organigrammes.', ['exception' => $exception]);
                $form->get('pdf')->addError(new FormError('Impossible de lire ce PDF. Réexportez-le en PDF non protégé, compatible PDF 1.4, puis réessayez.'));
            }
        }

        return $this->render('organigram/edit.html.twig', [
            'form' => $form->createView(),
            'hasPdf' => $this->currentImport($request, $config) !== null,
        ]);
    }

    private function currentImport(Request $request, OrganigramConfig $config): ?string
    {
        $id = $request->getSession()->get(self::SESSION_IMPORT);
        if (is_string($id) && $config->getImportPath($id) !== null) return $id;
        return null;
    }

    #[IsGranted('ROLE_ORGANIGRAM_EDIT')]
    #[Route('/organigramme/import/{id}/pdf', name: 'app_organigram_import_pdf', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function importPdf(string $id, Request $request, OrganigramConfig $config): Response
    {
        if ($id !== $this->currentImport($request, $config)) throw $this->createNotFoundException();
        $path = $config->getImportPath($id);
        if ($path === null) throw $this->createNotFoundException();
        return $this->pdfResponse($path, 'import.pdf', ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[IsGranted('ROLE_ORGANIGRAM_EDIT')]
    #[Route('/organigramme/configurer', name: 'app_organigram_configure', methods: ['GET'])]
    public function configure(Request $request, OrganigramConfig $config): Response
    {
        return $this->renderConfiguration($request, $config);
    }

    private function renderConfiguration(Request $request, OrganigramConfig $config, array $mapping = []): Response
    {
        $id = $this->currentImport($request, $config);
        return $this->render('organigram/configure.html.twig', [
            'pdfUrl' => $id !== null ? $this->generateUrl('app_organigram_import_pdf', ['id' => $id]) : null,
            'importId' => $id,
            'mapping' => $mapping,
        ]);
    }

    #[IsGranted('ROLE_ORGANIGRAM_EDIT')]
    #[Route('/organigramme/configurer', name: 'app_organigram_configure_save', methods: ['POST'])]
    public function configureSave(Request $request, OrganigramConfig $config, LoggerInterface $logger): Response
    {
        if (!$this->isCsrfTokenValid('organigram_configure', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $id = $this->currentImport($request, $config);
        if ($id === null || $request->request->get('import_id') !== $id) {
            $this->addFlash('error', 'Cet import n’est plus disponible. Rechargez la configuration.');
            return $this->redirectToRoute('app_organigram_configure');
        }
        $mapping = [];
        try {
            foreach (OrganigramConfig::KEYS as $key) {
                $value = $request->request->get($key);
                if ($value === null || $value === '') {
                    $mapping[$key] = null;
                } elseif (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                    $mapping[$key] = (int) $value;
                } else {
                    throw new \InvalidArgumentException('Numéro de page invalide.');
                }
            }
            $config->publishImport($id, $mapping);
        } catch (\Exception $exception) {
            $logger->error('Échec de la publication des organigrammes.', ['exception' => $exception]);
            $message = $exception instanceof \InvalidArgumentException
                ? $exception->getMessage()
                : 'Impossible d’extraire les pages de ce PDF. Les organigrammes actuels sont conservés. Réexportez le document en PDF non protégé, compatible PDF 1.4.';
            $this->addFlash('error', $message);
            return $this->renderConfiguration($request, $config, $mapping);
        }

        $config->discardImport($id);
        $request->getSession()->remove(self::SESSION_IMPORT);
        $this->addFlash('success', 'Les organigrammes sélectionnés ont été mis à jour. Les autres sont conservés.');
        return $this->redirectToRoute('app_organigram_edit');
    }
}
