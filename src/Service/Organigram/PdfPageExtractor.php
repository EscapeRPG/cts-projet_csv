<?php

namespace App\Service\Organigram;

use setasign\Fpdi\Fpdi;

final class PdfPageExtractor
{
    public function pageCount(string $path): int
    {
        $pdf = new Fpdi();
        try {
            return $pdf->setSourceFile($path);
        } finally {
            $pdf->cleanUp();
        }
    }

    public function extract(string $path, int $page): string
    {
        $pdf = new Fpdi();
        try {
            $count = $pdf->setSourceFile($path);
            if ($page < 1 || $page > $count) {
                throw new \InvalidArgumentException('La page sélectionnée est absente du PDF.');
            }
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            return $pdf->Output('S');
        } finally {
            $pdf->cleanUp();
        }
    }
}
