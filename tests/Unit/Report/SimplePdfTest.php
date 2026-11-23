<?php

namespace App\Tests\Unit\Report;

use App\Report\SimplePdf;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Le PDF écrit à la main doit rester un fichier valide : en-tête, table des références exacte, texte lisible. */
#[CoversClass(SimplePdf::class)]
final class SimplePdfTest extends TestCase
{
    public function testDocumentHasHeaderTrailerAndExactXref(): void
    {
        $vPdf = new SimplePdf('Titre');
        $vPdf->text('Bonjour');

        $vFile = $vPdf->render();

        self::assertStringStartsWith('%PDF-1.4', $vFile);
        self::assertStringEndsWith("%%EOF\n", $vFile);
        self::assertSame(1, preg_match('/startxref\n(\d+)\n/', $vFile, $vMatch));
        $vXref = (int) $vMatch[1];
        self::assertSame('xref', substr($vFile, $vXref, 4));
        // Chaque entrée de la table pointe sur « N 0 obj »
        preg_match_all('/^(\d{10}) 00000 n $/m', substr($vFile, $vXref), $vOffsets);
        self::assertNotEmpty($vOffsets[1]);
        foreach ($vOffsets[1] as $vIndex => $vOffset) {
            self::assertStringStartsWith(\sprintf('%d 0 obj', $vIndex + 1), substr($vFile, (int) $vOffset));
        }
    }

    public function testStreamLengthMatchesItsContent(): void
    {
        $vPdf = new SimplePdf('Titre');
        $vPdf->text('Une ligne');

        preg_match('/<< \/Length (\d+) >>\nstream\n(.*)\nendstream/s', $vPdf->render(), $vMatch);

        self::assertSame((int) $vMatch[1], \strlen($vMatch[2]));
    }

    public function testAccentsAreWrittenInWindows1252(): void
    {
        $vPdf = new SimplePdf('Titre');
        $vPdf->text('Évaluée à l\'été');

        self::assertStringContainsString("(\xC9valu\xE9e \xE0 l'\xE9t\xE9) Tj", $vPdf->render());
    }

    public function testPdfSpecialCharactersAreEscaped(): void
    {
        $vPdf = new SimplePdf('Titre (essai)');
        $vPdf->text('a (b) \\ c');

        $vFile = $vPdf->render();

        self::assertStringContainsString('(a \\(b\\) \\\\ c) Tj', $vFile);
        self::assertStringContainsString('/Title (Titre \\(essai\\))', $vFile);
    }

    public function testManyLinesFlowOverSeveralPages(): void
    {
        $vPdf = new SimplePdf('Titre');
        for ($vLine = 0; $vLine < 200; ++$vLine) {
            $vPdf->text('Ligne '.$vLine);
        }

        $vFile = $vPdf->render();

        self::assertGreaterThan(1, $vPdf->pageCount());
        self::assertStringContainsString(\sprintf('/Count %d', $vPdf->pageCount()), $vFile);
        self::assertSame($vPdf->pageCount(), substr_count($vFile, '/Type /Page /Parent'));
    }

    public function testShortContentStaysOnOnePage(): void
    {
        $vPdf = new SimplePdf('Titre');
        $vPdf->text('Une ligne');

        self::assertSame(1, $vPdf->pageCount());
    }

    public function testLongLineIsWrappedAtWordBoundaries(): void
    {
        $vPdf = new SimplePdf('Titre');
        $vPdf->text(str_repeat('mot ', 80));

        self::assertGreaterThan(1, substr_count($vPdf->render(), ' Tj'));
    }

    public function testWordLongerThanALineIsCutInsteadOfOverflowing(): void
    {
        $vPdf = new SimplePdf('Titre');
        $vPdf->text(str_repeat('x', 500));

        preg_match('/\((x+)\) Tj/', $vPdf->render(), $vMatch);

        self::assertLessThan(120, \strlen($vMatch[1]));
    }

    public function testBarRatioIsClampedBetweenZeroAndOne(): void
    {
        $vFull = new SimplePdf('T');
        $vFull->bar(7.0, 100.0);
        $vNone = new SimplePdf('T');
        $vNone->bar(-3.0, 100.0);

        // Au plus la largeur donnée ; aucune partie remplie à 0
        self::assertStringContainsString('0.18 0.45 0.30 rg 50 ', $vFull->render());
        self::assertStringContainsString(' 100 8 re f', $vFull->render());
        self::assertStringNotContainsString('0.18 0.45 0.30 rg', $vNone->render());
    }

    public function testWholeDocumentIsExact(): void
    {
        $vPdf = new SimplePdf('T');
        $vPdf->text('Bonjour', 12, true, 10);
        $vPdf->space(5);
        $vPdf->bar(0.5, 100, 8, 20);
        $vPdf->bar(0.0);

        $vStream = "BT /F2 12 Tf 60 775.2 Td (Bonjour) Tj ET\n0.85 0.85 0.85 rg 70 758.2 100 8 re f\n0.18 0.45 0.30 rg 70 758.2 50 8 re f\n0.85 0.85 0.85 rg 50 746.2 200 8 re f\n";
        self::assertSame(
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [7 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            ."4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n"
            ."5 0 obj\n<< /Title (T) /Producer (Cursus) >>\nendobj\n"
            ."6 0 obj\n<< /Length 154 >>\nstream\n".$vStream."\nendstream\nendobj\n"
            ."7 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            ."xref\n0 8\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000212 00000 n \n0000000314 00000 n \n0000000365 00000 n \n0000000570 00000 n \n"
            ."trailer\n<< /Size 8 /Root 1 0 R /Info 5 0 R >>\nstartxref\n706\n%%EOF\n",
            $vPdf->render(),
        );
    }

    public function testPageBreaksOnlyWhenTheLineWouldCrossTheBottomMargin(): void
    {
        $vFits = new SimplePdf('T');
        $vBreaks = new SimplePdf('T');
        for ($vLine = 0; $vLine < 53; ++$vLine) {
            $vFits->text('x');
            $vBreaks->text('x');
        }
        $vBreaks->text('x');

        // La 53e ligne se pose exactement sur la marge basse (y = 50) : elle tient encore
        self::assertSame(1, $vFits->pageCount());
        self::assertSame(2, $vBreaks->pageCount());
        $vFile = $vBreaks->render();
        self::assertStringContainsString('/Kids [7 0 R 9 0 R] /Count 2', $vFile);
        self::assertStringContainsString("BT /F1 10 Tf 50 50 Td (x) Tj ET\n\nendstream", $vFile);
        self::assertStringContainsString("BT /F1 10 Tf 50 778 Td (x) Tj ET\n\nendstream", $vFile);
        self::assertStringContainsString('/Contents 8 0 R', $vFile);
    }

    /** @return iterable<string, array{float, bool, float, list<int>}> */
    public static function wrapCases(): iterable
    {
        yield 'normal' => [10.0, false, 0.0, [95, 63]];
        yield 'gras plus large' => [10.0, true, 0.0, [87, 71]];
        yield 'retrait' => [10.0, false, 100.0, [75, 75, 7]];
    }

    /** @param list<int> $pLengths */
    #[DataProvider('wrapCases')]
    public function testLineWidthDependsOnSizeBoldAndIndent(float $pSize, bool $pBold, float $pIndent, array $pLengths): void
    {
        $vPdf = new SimplePdf('T');
        $vPdf->text(str_repeat('mot ', 40), $pSize, $pBold, $pIndent);

        preg_match_all('/\((.*?)\) Tj/', $vPdf->render(), $vMatch);

        self::assertSame($pLengths, array_map('strlen', $vMatch[1]));
    }

    public function testWordTooLongIsCutToExactlyOneLine(): void
    {
        $vPdf = new SimplePdf('T');
        $vPdf->text(str_repeat('x', 300));

        preg_match_all('/\((x+)\) Tj/', $vPdf->render(), $vMatch);

        self::assertSame([95], array_map('strlen', $vMatch[1]));
    }
}
