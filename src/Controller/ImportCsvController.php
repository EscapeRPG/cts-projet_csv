<?php

namespace App\Controller;

use App\Form\ImportCsvType;
use App\Import\ImportRouter;
use App\Service\ImportCentresClientsService;
use App\Service\ImportClientsControlesService;
use App\Service\ImportClientsService;
use App\Service\ImportControlesFacturesService;
use App\Service\ImportControlesNonFacturesService;
use App\Service\ImportControlesService;
use App\Service\ImportFacturesReglementsService;
use App\Service\ImportFacturesService;
use App\Service\ImportPrestasNonFactureesService;
use App\Service\ImportReglementsService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_IMPORT')]
class ImportCsvController extends AbstractController
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
                    }
                }
            }

            if ($total > 0) {
                $this->addFlash(
                    'success',
                    sprintf('%d lignes importées avec succès', $total)
                );
            }
        }

        return $this->render('import_csv/index.html.twig', [
            'form' => $form,
            'errors' => $form->getErrors(),
        ]);
    }
}
