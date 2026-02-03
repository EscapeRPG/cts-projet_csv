<?php

namespace App\Controller;

use App\Form\ImportCsvType;
use App\Import\ImportRouter;
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

#[IsGranted('ROLE_IMPORT')]
final class ImportCsvController extends AbstractController
{
    #[Route('/import', name: 'import_csv')]
    public function import(
        Request $request,
        ImportRouter $importRouter,
    ): Response {
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

        return $this->render('import_csv/index.html.twig', [
            'form' => $form,
            'errors' => $form->getErrors(),
        ]);
    }
}
