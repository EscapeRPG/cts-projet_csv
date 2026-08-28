<?php

namespace App\Controller;

use App\Form\ImportCsvType;
use App\Form\ImportAstikotoType;
use App\Import\ImportRouter;
use App\Repository\ImportedFilesRepository;
use App\Repository\ReseauRepository;
use App\Service\Import\ImportAstikotoFileService;
use App\Service\Import\ImportCentresClientsService;
use App\Service\Import\ImportClientsControlesService;
use App\Service\Import\ImportClientsService;
use App\Service\Import\ImportControlesFacturesService;
use App\Service\Import\ImportControlesNonFacturesService;
use App\Service\Import\ImportControlesService;
use App\Service\Import\ImportFacturesReglementsService;
use App\Service\Import\ImportFacturesService;
use App\Service\Import\ImportPrestasNonFactureesService;
use App\Service\Import\ImportReglementsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('ROLE_IMPORT')]
/**
 * Handles CSV upload and orchestrates business-ordered import execution.
 */
final class ImportCsvController extends AbstractController
{
    /**
     * Uploads CSV files and imports them using a predefined business execution order.
     *
     * @param Request $request Current HTTP request containing uploaded files.
     * @param ImportRouter $importRouter Router that resolves the correct importer for each file.
     *
     * @return Response Rendered import page with form state and optional success message.
     */
    #[Route('/cts/import', name: 'import_csv')]
    public function import(
        Request      $request,
        ImportRouter $importRouter,
    ): Response
    {
        $form = $this->createForm(ImportCsvType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $files = $form->get('files')->getData();
            $total = 0;

            $imports = [];

            foreach ($files as $file) {
                $importer = $importRouter->getImporterForFile(
                    $file->getClientOriginalName()
                );

                $imports[] = [$importer, $file];
            }

            /**
             * Ordre métier
             */
            $order = [
                ImportClientsService::class,
                ImportControlesService::class,
                ImportFacturesService::class,
                ImportReglementsService::class,
                ImportPrestasNonFactureesService::class,
                ImportCentresClientsService::class,
                ImportControlesNonFacturesService::class,
                ImportControlesFacturesService::class,
                ImportFacturesReglementsService::class,
                ImportClientsControlesService::class
            ];

            foreach ($order as $serviceClass) {
                foreach ($imports as [$importer, $file]) {
                    if ($importer instanceof $serviceClass) {
                        $importer->importFromFile($file);
                        $total++;
                    }
                }
            }

            if ($total > 0) {
                $message = $total . ($total > 1 ? ' fichiers importés' : ' fichier importé') . ' avec succès.';
                $this->addFlash(
                    'success',
                    $message
                );
            }
        }

        return $this->render('cts/import_csv/index.html.twig', [
            'form' => $form,
            'errors' => $form->getErrors(),
        ]);
    }

    /**
     * Uploads internal Astikoto CSV files.
     *
     * @param Request $request Current HTTP request containing uploaded files.
     *
     * @return Response Rendered Astikoto import page.
     */
    #[Route('/astikoto/import', name: 'astikoto_import_csv')]
    public function importAstikoto(
        Request $request,
        ImportAstikotoFileService $importAstikotoFileService,
        ImportedFilesRepository $importedFilesRepository,
        ReseauRepository $reseauRepository,
    ): Response
    {
        $form = $this->createForm(ImportAstikotoType::class);
        $form->handleRequest($request);
        $reseau = $reseauRepository->findOneBy(['nom' => 'astikoto']);
        $selectedCentre = $form->get('centre')->getData();

        if ($form->isSubmitted() && $form->isValid()) {
            $files = $form->get('files')->getData();
            $centre = $selectedCentre;
            $total = 0;
            $skipped = 0;

            if ($reseau === null) {
                $this->addFlash(
                    'error',
                    'Le réseau « astikoto » doit être créé avant de pouvoir importer ces fichiers.'
                );
            } else {
                foreach ($files as $file) {
                    try {
                        $rowsRead = $importAstikotoFileService->importFromFileForCentre($file, $reseau, $centre);

                        if ($rowsRead === 0) {
                            $skipped++;
                            continue;
                        }

                        $total++;
                    } catch (Throwable $exception) {
                        $this->addFlash(
                            'error',
                            sprintf(
                                'Impossible d’importer « %s » : %s',
                                $file->getClientOriginalName(),
                                $exception->getMessage()
                            )
                        );
                    }
                }
            }

            if ($total > 0) {
                $message = $total . ($total > 1 ? ' fichiers importés' : ' fichier importé') . ' avec succès.';
                $this->addFlash(
                    'success',
                    $message
                );
            }

            if ($skipped > 0) {
                $this->addFlash(
                    'warning',
                    $skipped . ($skipped > 1
                        ? ' fichiers avaient déjà été importés.'
                        : ' fichier avait déjà été importé.')
                );
            }
        }

        $latestImports = [
            4 => $reseau === null
                ? null
                : $importedFilesRepository->findLatestForReseauAndPortique($reseau, 4, $selectedCentre),
            5 => $reseau === null
                ? null
                : $importedFilesRepository->findLatestForReseauAndPortique($reseau, 5, $selectedCentre),
        ];

        return $this->render('astikoto/import_csv/index.html.twig', [
            'form' => $form,
            'errors' => $form->getErrors(),
            'latestImports' => $latestImports,
        ]);
    }
}
