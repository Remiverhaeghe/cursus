<?php

namespace App\Tests\Unit\Report;

use App\Report\SimplePdf;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
