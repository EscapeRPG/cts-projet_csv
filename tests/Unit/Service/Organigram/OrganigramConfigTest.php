<?php

namespace App\Tests\Unit\Service\Organigram;

use App\Controller\OrganigramController;
use App\Service\Organigram\OrganigramConfig;
use App\Service\Organigram\PdfPageExtractor;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Psr\Log\NullLogger;

final class OrganigramConfigTest extends TestCase
{
    private string $directory;
    private OrganigramConfig $config;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/organigram-test-' . bin2hex(random_bytes(8));
        $this->fs = new Filesystem();
        $this->fs->mkdir($this->directory);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->directory);
        $this->config = new OrganigramConfig($kernel, $this->fs, new PdfPageExtractor());
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->directory);
    }

    public function testImportsPublishIndividualPagesAndOnlyReplaceSelectedTypes(): void
    {
        $first = $this->stage([[210, 297], [400, 200]]);
        self::assertNull($this->config->getDocumentPath('structurel'));
        $this->config->publishImport($first, ['structurel' => 1, 'immobilier' => 2]);
        $structurel = $this->config->getDocumentPath('structurel');
        $immobilier = $this->config->getDocumentPath('immobilier');
        $this->assertPageSize($structurel, 210, 297);
        $this->assertPageSize($immobilier, 400, 200);

        $second = $this->stage([[300, 150]]);
        $this->config->publishImport($second, ['structurel' => null, 'hierarchique' => 1]);
        self::assertSame($structurel, $this->config->getDocumentPath('structurel'));
        self::assertSame($immobilier, $this->config->getDocumentPath('immobilier'));
        $this->assertPageSize($this->config->getDocumentPath('hierarchique'), 300, 150);

        $this->config->publishImport($second, ['structurel' => 1]);
        self::assertNotSame($structurel, $this->config->getDocumentPath('structurel'));
        self::assertSame($immobilier, $this->config->getDocumentPath('immobilier'));
        $this->config->discardImport($second);
        self::assertNull($this->config->getImportPath($second));
        $this->assertPageSize($this->config->getDocumentPath('structurel'), 300, 150);
    }

    public function testInvalidPageLeavesAllPublishedDocumentsUnchanged(): void
    {
        $first = $this->stage([[210, 297]]);
        $this->config->publishImport($first, ['structurel' => 1]);
        $path = $this->config->getDocumentPath('structurel');
        $manifest = file_get_contents($this->directory . '/var/organigram/published.json');
        try {
            $this->config->publishImport($first, ['structurel' => 1, 'immobilier' => 2]);
            self::fail('An out-of-range page must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertSame($manifest, file_get_contents($this->directory . '/var/organigram/published.json'));
            self::assertSame($path, $this->config->getDocumentPath('structurel'));
            self::assertNull($this->config->getDocumentPath('immobilier'));
        }
    }

    public function testEmptySelectionIsRejected(): void
    {
        $id = $this->stage([[210, 297]]);
        $this->expectException(\InvalidArgumentException::class);
        $this->config->publishImport($id, ['structurel' => null]);
    }

    public function testLegacyDocumentsSurviveNewImportsAndAreNeverRestoredOverReplacements(): void
    {
        $this->config->ensureBaseDir();
        $this->makePdf($this->config->getPdfPath(), [[210, 297], [400, 200]]);
        $this->config->saveMapping(['structurel' => 1, 'immobilier' => 2]);
        $legacyHash = hash_file('sha256', $this->config->getPdfPath());
        $id = $this->stage([[300, 150]]);
        $this->config->publishImport($id, ['hierarchique' => 1]);
        $this->assertPageSize($this->config->getDocumentPath('structurel'), 210, 297);
        $this->assertPageSize($this->config->getDocumentPath('immobilier'), 400, 200);
        $this->config->publishImport($id, ['structurel' => 1]);
        $this->assertPageSize($this->config->getDocumentPath('structurel'), 300, 150);
        self::assertSame($legacyHash, hash_file('sha256', $this->config->getPdfPath()));
    }

    public function testInvalidPdfDoesNotBecomeAStagedImport(): void
    {
        $path = $this->directory . '/invalid.pdf';
        file_put_contents($path, 'Not a PDF');
        try {
            $this->config->stageImport(new UploadedFile($path, 'invalid.pdf', 'application/pdf', null, true));
            self::fail('Invalid PDF must be rejected.');
        } catch (\setasign\Fpdi\PdfParser\PdfParserException) {
            self::assertDirectoryDoesNotExist($this->directory . '/var/organigram/imports');
        }
    }

    public function testImportIdentifiersCannotEscapeStorage(): void
    {
        self::assertNull($this->config->getImportPath('../../composer.json'));
    }

    public function testDownloadIsAvailableWithOnlyTheRelevantViewRole(): void
    {
        $id = $this->stage([[210, 297], [400, 200]]);
        $this->config->publishImport($id, ['immobilier' => 2]);
        $response = $this->controllerWithViewAccess(true)->download('immobilier', $this->config);
        self::assertSame('attachment; filename=immobilier.pdf', $response->headers->get('Content-Disposition'));
        $this->assertPageSize($response->getFile()->getPathname(), 400, 200);
    }

    public function testDownloadCannotBypassTheOrganigramViewRole(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->controllerWithViewAccess(false)->download('immobilier', $this->config);
    }

    public function testPreviewCannotReadAnotherSessionsImport(): void
    {
        $mine = $this->stage([[210, 297]]);
        $other = $this->stage([[400, 200]]);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('organigram_import', $mine);
        $this->expectException(NotFoundHttpException::class);
        (new OrganigramController())->importPdf($other, $request, $this->config);
    }

    public function testStaleFormDoesNotPublishTheNewImportsPages(): void
    {
        $old = $this->stage([[210, 297]]);
        $new = $this->stage([[400, 200]]);
        [$controller, $request] = $this->publicationRequest($new, ['import_id' => $old, 'structurel' => '1']);
        $controller->configureSave($request, $this->config, new NullLogger());
        self::assertNull($this->config->getDocumentPath('structurel'));
        self::assertNotEmpty($request->getSession()->getFlashBag()->get('error'));
    }

    public function testSuccessfulFormPublishesAndRemovesOnlyItsStagedFile(): void
    {
        $id = $this->stage([[210, 297]]);
        $other = $this->stage([[400, 200]]);
        [$controller, $request] = $this->publicationRequest($id, ['import_id' => $id, 'structurel' => '1']);
        $controller->configureSave($request, $this->config, new NullLogger());
        $this->assertPageSize($this->config->getDocumentPath('structurel'), 210, 297);
        self::assertNull($request->getSession()->get('organigram_import'));
        self::assertNull($this->config->getImportPath($id));
        self::assertNotNull($this->config->getImportPath($other));
    }

    private function publicationRequest(string $currentId, array $parameters): array
    {
        $request = Request::create('/', 'POST', $parameters + ['_token' => 'valid']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('organigram_import', $currentId);
        $stack = new RequestStack();
        $stack->push($request);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/organigramme/editer');
        $container = new Container();
        $container->set('request_stack', $stack);
        $container->set('security.csrf.token_manager', $csrf);
        $container->set('router', $router);
        $controller = new OrganigramController();
        $controller->setContainer($container);
        return [$controller, $request];
    }

    private function controllerWithViewAccess(bool $granted): OrganigramController
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())->method('isGranted')->with('ROLE_ORGANIGRAM_IMMO_VIEW', null)->willReturn($granted);
        $container = new Container();
        $container->set('security.authorization_checker', $checker);
        $controller = new OrganigramController();
        $controller->setContainer($container);
        return $controller;
    }

    private function stage(array $sizes): string
    {
        $path = $this->directory . '/' . bin2hex(random_bytes(8)) . '.pdf';
        $this->makePdf($path, $sizes);
        return $this->config->stageImport(new UploadedFile($path, 'source.pdf', 'application/pdf', null, true));
    }

    private function makePdf(string $path, array $sizes): void
    {
        $pdf = new \FPDF();
        foreach ($sizes as [$width, $height]) {
            $pdf->AddPage($width > $height ? 'L' : 'P', [$width, $height]);
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Text(10, 20, 'Page ' . $pdf->PageNo());
        }
        $pdf->Output('F', $path);
    }

    private function assertPageSize(string $path, float $width, float $height): void
    {
        $pdf = new Fpdi();
        try {
            self::assertSame(1, $pdf->setSourceFile($path));
            $size = $pdf->getTemplateSize($pdf->importPage(1));
            self::assertEqualsWithDelta($width, $size['width'], 0.1);
            self::assertEqualsWithDelta($height, $size['height'], 0.1);
        } finally {
            $pdf->cleanUp();
        }
    }
}
