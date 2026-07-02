<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ImportHealthController extends AbstractController
{
    #[Route('/admin/imports/suivi', name: 'app_import_health')]
    public function index(Connection $connection): Response
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT check_date, reseau_name, files_imported, expected_files, controles_files, latest_imported_at, status, issues
             FROM import_health_check
             ORDER BY check_date DESC, reseau_name ASC'
        );

        foreach ($rows as &$row) {
            $decoded = json_decode((string)$row['issues'], true);
            $row['issues_list'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return $this->render('admin/import_health/index.html.twig', [
            'rows' => $rows,
        ]);
    }
}
