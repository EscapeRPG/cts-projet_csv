<?php

namespace App\Controller;

use App\Entity\Voiture;
use App\Enum\TypeCentre;
use App\Form\CreateVoitureType;
use App\Repository\CentreRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoitureRepository;
use App\Service\List\ActiveStatusFilter;
use App\Service\List\BulkUpdateProcessor;
use App\Service\Pagination\PaginationResolver;
use App\Service\Security\UserScopeResolver;
use App\Service\Voiture\VoitureCertificatCessionStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class VoitureController extends AbstractController
{
    private const string CSRF_FIELD_NAME = '_token';

    public function __construct(
        private readonly UserScopeResolver $scopeResolver,
        private readonly PaginationResolver $paginationResolver
    )
    {
    }

    /**
     * Displays vehicles (uneditable).
     */
    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list-voitures", name: 'app_voitures_list_uneditable')]
    public function listVoituresNonEditable(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE), $activeStatus->includeActive, $activeStatus->includeInactive);

        return $this->render('cts/voitures/list_uneditable.html.twig', [
            'voitures' => $voitures,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list-voitures/partial", name: 'app_voitures_list_uneditable_partial')]
    public function listVoituresNonEditablePartial(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE), $activeStatus->includeActive, $activeStatus->includeInactive);

        return $this->render('cts/voitures/_list_results_uneditable.html.twig', [
            'voitures' => $voitures,
        ]);
    }

    /**
     * Displays vehicles with one inline edit form per row.
     */
    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list", name: 'app_voitures_list')]
    public function listVoitures(
        Request           $request,
        VoitureRepository $voitureRepository,
        SocieteRepository $societeRepository,
        CentreRepository  $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch(null, $centreIds);

        return $this->render('cts/voitures/list.html.twig', [
            'voitures' => $voitures,
            'societes' => $societes,
            'centres' => $centres,
            'pagination' => null,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route("/cts/voitures/list/partial", name: 'app_voitures_list_partial')]
    public function listVoituresPartial(
        Request           $request,
        VoitureRepository $voitureRepository,
        SocieteRepository $societeRepository,
        CentreRepository  $centreRepository,
    ): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);
        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch(null, $centreIds);

        return $this->render('cts/voitures/_list_results.html.twig', [
            'voitures' => $voitures,
            'societes' => $societes,
            'centres' => $centres,
            'pagination' => null,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route("/cts/voitures/add", name: 'app_voitures_add')]
    public function addVoiture(
        Request                         $request,
        EntityManagerInterface          $em,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);

        $form = $this->createForm(CreateVoitureType::class, null, [
            'centre_scope_ids' => $centreIds,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            try {
                if (!$form->isValid()) {
                    return $this->render('cts/voitures/add.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
            } catch (FileException $e) {
                $this->addFlash('error', self::formatUploadRuntimeException($e));
                return $this->redirectToRoute('app_voitures_add');
            }

            $voiture = $form->getData();

            $em->persist($voiture);
            $em->flush();

            $uploaded = $form->get('certificatCessionFile')->getData();
            if ($uploaded instanceof UploadedFile) {
                if (!$uploaded->isValid()) {
                    $this->addFlash('error', self::formatUploadErrorMessage($uploaded->getError()));
                    return $this->redirectToRoute('app_voitures_list_uneditable');
                }

                // Read metadata before move(): after moving, the tmp file no longer exists.
                $originalName = $uploaded->getClientOriginalName();
                $mime = (string)($uploaded->getMimeType() ?? '');
                $size = $uploaded->getSize();

                $relativePath = $storage->storeCertificat($voiture, $uploaded);
                $voiture->setCertificatCessionPath($relativePath);
                $voiture->setCertificatCessionOriginalName($originalName);
                $voiture->setCertificatCessionMime($mime !== '' ? $mime : null);
                $voiture->setCertificatCessionSize($size);
                $voiture->setCertificatCessionUploadedAt(new \DateTimeImmutable());
                $em->flush();
            }

            $this->addFlash('success', 'Voiture créée.');

            return $this->redirectToRoute('app_voitures_list_uneditable');
        }

        return $this->render('cts/voitures/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_EDIT')]
    #[Route('/cts/voitures/bulk-update', name: 'app_voitures_bulk_update', methods: ['POST'])]
    public function bulkUpdateVoitures(
        Request                $request,
        VoitureRepository      $voitureRepository,
        SocieteRepository      $societeRepository,
        CentreRepository       $centreRepository,
        BulkUpdateProcessor    $bulkUpdateProcessor,
        EntityManagerInterface $em,
    ): Response
    {
        if (!$this->isCsrfTokenValid('voitures_bulk', (string)$request->request->get(self::CSRF_FIELD_NAME, ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide, rechargez la page.');
            return $this->redirectToRoute('app_voitures_list', [
                'q' => $request->query->get('q'),
                'active' => $request->query->getInt('active', 1),
                'inactive' => $request->query->getInt('inactive', 0),
            ]);
        }

        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);

        $q = trim((string)$request->query->get('q', ''));
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);

        $societes = $societeRepository->findOrderedByNomSearch(null, $centreIds);
        $centres = $centreRepository->findCtsOrderedBySocieteVilleAgrSearch(null, $centreIds);

        $payload = $request->request->all('changes');
        if ($payload === []) {
            $this->addFlash('warning', 'Aucune modification détectée.');
            return $this->redirectToRoute('app_voitures_list', [
                'q' => $request->query->get('q'),
                ...$activeStatus->queryParameters(),
            ]);
        }

        $societeIds = array_fill_keys(array_filter(array_map(static fn($s) => $s->getId(), $societes)), true);
        $centreAllowedIds = array_fill_keys(array_filter(array_map(static fn($c) => $c->getId(), $centres)), true);

        $result = $bulkUpdateProcessor->process(
            $voitures,
            $payload,
            fn(Voiture $voiture, array $fields): array => $this->applyManualVoitureUpdate(
                $voiture,
                $fields,
                $societeRepository,
                $centreRepository,
                $societeIds,
                $centreAllowedIds,
            ),
            static fn(Voiture $voiture): string => (string) $voiture->getImmatriculation(),
        );

        if ($result->errors !== []) {
            foreach ($result->errors as $msg) {
                $this->addFlash('error', $msg);
            }
            return $this->render('cts/voitures/list.html.twig', [
                'voitures' => $voitures,
                'societes' => $societes,
                'centres' => $centres,
                'pagination' => null,
            ]);
        }

        if ($result->changedCount === 0) {
            $this->addFlash('warning', 'Aucune modification appliquée.');
            return $this->redirectToRoute('app_voitures_list', [
                'q' => $request->query->get('q'),
                ...$activeStatus->queryParameters(),
            ]);
        }

        $em->flush();
        $this->addFlash('success', 'Modifications enregistrées.');

        return $this->redirectToRoute('app_voitures_list_uneditable', [
            'q' => $request->query->get('q'),
            ...$activeStatus->queryParameters(),
        ]);
    }

    private static function formatUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Upload impossible: fichier trop volumineux. Vérifiez upload_max_filesize et post_max_size.',
            \UPLOAD_ERR_PARTIAL => 'Upload incomplet: le fichier n\'a été que partiellement envoyé.',
            \UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été envoyé.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Upload impossible: dossier temporaire manquant côté serveur.',
            \UPLOAD_ERR_CANT_WRITE => 'Upload impossible: écriture sur disque impossible côté serveur.',
            \UPLOAD_ERR_EXTENSION => 'Upload bloqué par une extension PHP côté serveur.',
            default => 'Upload impossible: erreur inconnue.',
        };
    }

    private static function formatUploadRuntimeException(\Symfony\Component\HttpFoundation\File\Exception\FileException $e): string
    {
        $openBasedir = (string)ini_get('open_basedir');
        $uploadTmpDir = (string)ini_get('upload_tmp_dir');

        $base = 'Upload impossible: le fichier temporaire n\'est pas accessible côté serveur.';
        $details = trim($e->getMessage());

        $hint = [];
        if ($openBasedir !== '') {
            $hint[] = "open_basedir est actif ({$openBasedir}).";
        }
        if ($uploadTmpDir !== '') {
            $hint[] = "upload_tmp_dir={$uploadTmpDir}.";
        }
        $hint[] = 'Solution: ajouter le dossier temporaire PHP dans open_basedir, ou configurer upload_tmp_dir vers un dossier accessible (ex: <projet>/var/tmp).';

        return $base . ' ' . $details . ' ' . implode(' ', $hint);
    }

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route('/cts/voitures/{id}/certificat-cession/upload', name: 'app_voitures_certificat_upload', methods: ['POST'])]
    public function uploadVoitureCertificatCession(
        Voiture                         $voiture,
        Request                         $request,
        EntityManagerInterface          $em,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $token = (string)$request->request->get(self::CSRF_FIELD_NAME, '');
        if (!$this->isCsrfTokenValid('voiture_certificat_upload_' . $voiture->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        $file = $request->files->get('certificat');
        if (!$file) {
            $this->addFlash('warning', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $this->addFlash('error', 'Fichier invalide.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        // Read all metadata before storeCertificat(): move() removes the PHP
        // temporary file, so SplFileInfo methods such as getSize() then fail.
        $originalName = $file->getClientOriginalName();
        $mime = (string)($file->getMimeType() ?? '');
        $size = $file->getSize();
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mime, $allowed, true)) {
            $this->addFlash('error', 'Format non autorisé. Formats acceptés: PDF, JPG, PNG.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }
        if ($size !== null && $size > 15 * 1024 * 1024) {
            $this->addFlash('error', 'Fichier trop volumineux (15 Mo max).');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        // Replace existing certificate if any.
        $storage->deleteIfExists($voiture->getCertificatCessionPath());

        $relativePath = $storage->storeCertificat($voiture, $file);
        $voiture->setCertificatCessionPath($relativePath);
        $voiture->setCertificatCessionOriginalName($originalName);
        $voiture->setCertificatCessionMime($mime !== '' ? $mime : null);
        $voiture->setCertificatCessionSize($size);
        $voiture->setCertificatCessionUploadedAt(new \DateTimeImmutable());

        $em->persist($voiture);
        $em->flush();

        $this->addFlash('success', 'Certificat de cession uploadé.');

        return $this->redirectToRoute('app_voitures_list', [
            'page' => $request->query->getInt('page', 1),
            'q' => $request->query->get('q'),
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route('/cts/voitures/{id}/certificat-cession/download', name: 'app_voitures_certificat_download', methods: ['GET'])]
    public function downloadVoitureCertificatCession(
        Voiture                         $voiture,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $relative = $voiture->getCertificatCessionPath();
        if (!$relative) {
            throw $this->createNotFoundException('Aucun certificat associé à cette voiture.');
        }

        $absolute = $storage->absolutePath($relative);
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $immatriculation = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$voiture->getImmatriculation());
        $immatriculation = trim((string)$immatriculation, '_');
        $downloadName = $immatriculation !== '' ? ('certificat_cession_' . $immatriculation) : 'certificat_cession';

        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        if ($ext) {
            $downloadName .= '.' . $ext;
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route('/cts/voitures/{id}/certificat-cession/view', name: 'app_voitures_certificat_view', methods: ['GET'])]
    public function viewVoitureCertificatCession(
        Voiture                         $voiture,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $relative = $voiture->getCertificatCessionPath();
        if (!$relative) {
            throw $this->createNotFoundException('Aucun certificat associé à cette voiture.');
        }

        $absolute = $storage->absolutePath($relative);
        if (!is_file($absolute)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolute);

        $immatriculation = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$voiture->getImmatriculation());
        $immatriculation = trim((string)$immatriculation, '_');
        $name = $immatriculation !== '' ? ('certificat_cession_' . $immatriculation) : 'certificat_cession';
        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        if ($ext) {
            $name .= '.' . $ext;
        }

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $name);

        $mime = $voiture->getCertificatCessionMime();
        if (is_string($mime) && $mime !== '') {
            $response->headers->set('Content-Type', $mime);
        }

        return $response;
    }

    #[IsGranted('ROLE_LIST_VOITURES_ADD')]
    #[Route('/cts/voitures/{id}/certificat-cession/delete', name: 'app_voitures_certificat_delete', methods: ['POST'])]
    public function deleteVoitureCertificatCession(
        Voiture                         $voiture,
        Request                         $request,
        EntityManagerInterface          $em,
        VoitureCertificatCessionStorage $storage,
    ): Response
    {
        $token = (string)$request->request->get(self::CSRF_FIELD_NAME, '');
        if (!$this->isCsrfTokenValid('voiture_certificat_delete_' . $voiture->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_voitures_list', [
                'page' => $request->query->getInt('page', 1),
                'q' => $request->query->get('q'),
            ]);
        }

        $storage->deleteIfExists($voiture->getCertificatCessionPath());
        $voiture->setCertificatCessionPath(null);
        $voiture->setCertificatCessionOriginalName(null);
        $voiture->setCertificatCessionMime(null);
        $voiture->setCertificatCessionSize(null);
        $voiture->setCertificatCessionUploadedAt(null);

        $em->persist($voiture);
        $em->flush();

        $this->addFlash('success', 'Certificat supprimé.');

        return $this->redirectToRoute('app_voitures_list', [
            'page' => $request->query->getInt('page', 1),
            'q' => $request->query->get('q'),
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route('/cts/voitures/list/print', name: 'app_voitures_list_print')]
    public function listVoituresPrint(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $centreIds = $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE);
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $perPage = 20;
        $paginationData = $this->paginationResolver->computePagination($request, $perPage, $voitureRepository->countSearch($q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive));
        $voitures = $voitureRepository->findPaginatedOrderedBySocieteSearch($perPage, $paginationData['offset'], $q, $centreIds, $activeStatus->includeActive, $activeStatus->includeInactive);

        return $this->render('cts/lists/print/voitures.html.twig', [
            'voitures' => $voitures,
            'pagination' => $paginationData['view'],
            'q' => $q,
            'printTitle' => 'Liste des voitures',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
        ]);
    }

    #[IsGranted('ROLE_LIST_VOITURES_VIEW')]
    #[Route('/cts/voitures/list-voitures/print', name: 'app_voitures_list_uneditable_print')]
    public function listVoituresNonEditablePrint(Request $request, VoitureRepository $voitureRepository): Response
    {
        $q = trim((string)$request->query->get('q', ''));
        $activeStatus = ActiveStatusFilter::fromRequest($request);
        $voitures = $voitureRepository->findOrderedBySocieteSearch($q, $this->scopeResolver->centreIds(TypeCentre::CONTROLE_TECHNIQUE), $activeStatus->includeActive, $activeStatus->includeInactive);

        return $this->render('cts/lists/print/voitures.html.twig', [
            'voitures' => $voitures,
            'pagination' => null,
            'q' => $q,
            'printTitle' => 'Liste des voitures',
            'printVariant' => 'lists',
            'printOrientation' => 'landscape',
            'autoPrint' => true,
        ]);
    }

    /**
     * @param array<int, mixed> $fields
     * @param array<int, true> $allowedSocieteIds
     * @param array<int, true> $allowedCentreIds
     * @return list<string> Row errors.
     */
    private function applyManualVoitureUpdate(
        Voiture           $voiture,
        array             $fields,
        SocieteRepository $societeRepository,
        CentreRepository  $centreRepository,
        array             $allowedSocieteIds,
        array             $allowedCentreIds,
    ): array
    {
        $errors = [];

        $trimOrNull = static function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string)$v);
            return $s === '' ? null : $s;
        };

        $societeId = $trimOrNull($fields['societe'] ?? null);
        if ($societeId === null || !ctype_digit($societeId)) {
            $errors[] = 'Société invalide.';
        } else {
            $sid = (int)$societeId;
            if (!isset($allowedSocieteIds[$sid])) {
                $errors[] = 'Société hors scope.';
            } else {
                $societe = $societeRepository->find($sid);
                if (!$societe instanceof \App\Entity\Societe) {
                    $errors[] = 'Société introuvable.';
                } else {
                    $voiture->setSociete($societe);
                }
            }
        }

        $centreId = $trimOrNull($fields['centre'] ?? null);
        if ($centreId === null || !ctype_digit($centreId)) {
            $errors[] = 'Centre invalide.';
        } else {
            $cid = (int)$centreId;
            if (!isset($allowedCentreIds[$cid])) {
                $errors[] = 'Centre hors scope.';
            } else {
                $centre = $centreRepository->find($cid);
                if (!$centre instanceof \App\Entity\Centre || !$centre->isControleTechnique()) {
                    $errors[] = 'Centre introuvable.';
                } else {
                    $voiture->setCentre($centre);
                }
            }
        }

        // Non-nullable in DB: keep empty strings if not provided.
        $voiture->setImmatriculation((string)($trimOrNull($fields['immatriculation'] ?? null) ?? ''));
        $voiture->setMarque((string)($trimOrNull($fields['marque'] ?? null) ?? ''));
        $voiture->setCouleur($trimOrNull($fields['couleur'] ?? null));
        $voiture->setModele($trimOrNull($fields['modele'] ?? null));

        $flocable = $fields['flocable'] ?? null;
        if (is_array($flocable)) {
            $voiture->setFlocable(in_array('1', array_map('strval', $flocable), true));
        } else {
            $voiture->setFlocable((bool)($flocable === '1' || $flocable === 1 || $flocable === true || $flocable === 'on'));
        }

        $annee = $trimOrNull($fields['annee'] ?? null);
        if ($annee !== null) {
            if (!ctype_digit($annee) || strlen($annee) !== 4) {
                $errors[] = 'Année invalide.';
            } else {
                $voiture->setAnnee($annee);
            }
        } else {
            $voiture->setAnnee(null);
        }

        $ct = $trimOrNull($fields['controleTechnique'] ?? null);
        if ($ct !== null) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ct);
            if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $ct) {
                $errors[] = 'Date contrôle technique invalide.';
            } else {
                $voiture->setControleTechnique($dt);
            }
        } else {
            $voiture->setControleTechnique(null);
        }

        $voiture->setKm($trimOrNull($fields['km'] ?? null));

        $prix = $trimOrNull($fields['prix'] ?? null);
        if ($prix !== null) {
            $prix = str_replace(',', '.', $prix);
            if (!is_numeric($prix)) {
                $errors[] = 'Prix invalide.';
            } else {
                $voiture->setPrix((string)$prix);
            }
        } else {
            $voiture->setPrix(null);
        }

        $voiture->setCarteGrise($trimOrNull($fields['carteGrise'] ?? null));
        $voiture->setLieu($trimOrNull($fields['lieu'] ?? null));
        $voiture->setUtilisateur($trimOrNull($fields['utilisateur'] ?? null));
        $voiture->setRemarques($trimOrNull($fields['remarques'] ?? null));

        $active = $fields['active'] ?? null;
        if (is_array($active)) {
            $voiture->setActive(in_array('1', array_map('strval', $active), true));
        } else {
            $voiture->setActive((bool)($active === '1' || $active === 1 || $active === true || $active === 'on'));
        }

        return $errors;
    }
}
