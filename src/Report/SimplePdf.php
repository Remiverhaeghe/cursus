<?php

namespace App\Report;

/**
 * Écrivain de PDF minimal, sans dépendance : du texte en Helvetica (normal ou gras, accents compris) et des barres
 * pleines, sur des pages A4 qui se suivent. Les mots sont du vrai texte, que le lecteur peut chercher et copier.
 */
final class SimplePdf
{
    public const float PAGE_WIDTH = 595.0;
    public const float PAGE_HEIGHT = 842.0;
    public const float MARGIN = 50.0;

    /** @var list<string> flux de contenu, un par page */
    private array $aPages = [];

    private string $aContent = '';

    private float $aY;

    public function __construct(private readonly string $aTitle)
    {
        $this->aY = self::PAGE_HEIGHT - self::MARGIN;
    }

    /** Écrit une ligne ; elle est coupée au mot près si elle dépasse la largeur utile. */
    public function text(string $pText, float $pSize = 10.0, bool $pBold = false, float $pIndent = 0.0): void
    {
        $vLeading = $pSize * 1.4;
        foreach ($this->wrap($pText, $pSize, $pBold, self::PAGE_WIDTH - 2 * self::MARGIN - $pIndent) as $vLine) {
            $this->room($vLeading);
            $this->aY -= $vLeading;
            $this->aContent .= \sprintf(
                "BT /%s %s Tf %s %s Td (%s) Tj ET\n",
                $pBold ? 'F2' : 'F1',
                $this->number($pSize),
                $this->number(self::MARGIN + $pIndent),
                $this->number($this->aY),
                $this->escape($vLine),
            );
        }
    }

    public function space(float $pPoints): void
    {
        $this->aY -= $pPoints;
    }

    /** Barre de progression : fond gris puis partie remplie ({@see $pRatio} entre 0 et 1). */
    public function bar(float $pRatio, float $pWidth = 200.0, float $pHeight = 8.0, float $pIndent = 0.0): void
    {
        $this->room($pHeight + 4);
        $this->aY -= $pHeight + 4;
        $vRatio = max(0.0, min(1.0, $pRatio));
        $vX = self::MARGIN + $pIndent;
        $this->aContent .= \sprintf("0.85 0.85 0.85 rg %s %s %s %s re f\n", $this->number($vX), $this->number($this->aY), $this->number($pWidth), $this->number($pHeight));
        if ($vRatio > 0.0) {
            $this->aContent .= \sprintf("0.18 0.45 0.30 rg %s %s %s %s re f\n", $this->number($vX), $this->number($this->aY), $this->number($pWidth * $vRatio), $this->number($pHeight));
        }
    }

    /** Document complet, prêt à envoyer. */
    public function render(): string
    {
        $vPages = $this->aPages;
        $vPages[] = $this->aContent;

        // Objets : 1 catalogue, 2 pages, 3 et 4 polices, 5 infos, puis une paire contenu/page par page
        $vObjects = [];
        $vKids = [];
        foreach ($vPages as $vIndex => $vContent) {
            $vContentId = 6 + 2 * $vIndex;
            $vPageId = $vContentId + 1;
            $vObjects[$vContentId] = \sprintf("<< /Length %d >>\nstream\n%s\nendstream", \strlen($vContent), $vContent);
            $vObjects[$vPageId] = \sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->number(self::PAGE_WIDTH),
                $this->number(self::PAGE_HEIGHT),
                $vContentId,
            );
            $vKids[] = $vPageId.' 0 R';
        }
        $vObjects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $vObjects[2] = \sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $vKids), \count($vKids));
        $vObjects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $vObjects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $vObjects[5] = \sprintf('<< /Title (%s) /Producer (Cursus) >>', $this->escape($this->aTitle));
        ksort($vObjects);

        $rPdf = "%PDF-1.4\n";
        $vOffsets = [];
        foreach ($vObjects as $vId => $vBody) {
            $vOffsets[$vId] = \strlen($rPdf);
            $rPdf .= \sprintf("%d 0 obj\n%s\nendobj\n", $vId, $vBody);
        }
        $vXref = \strlen($rPdf);
        $rPdf .= \sprintf("xref\n0 %d\n0000000000 65535 f \n", \count($vObjects) + 1);
        foreach ($vOffsets as $vOffset) {
            $rPdf .= \sprintf("%010d 00000 n \n", $vOffset);
        }

        return $rPdf.\sprintf("trailer\n<< /Size %d /Root 1 0 R /Info 5 0 R >>\nstartxref\n%d\n%%%%EOF\n", \count($vObjects) + 1, $vXref);
    }

    public function pageCount(): int
    {
        return \count($this->aPages) + 1;
    }

    /** Passe à une nouvelle page quand il ne reste pas la place demandée. */
    private function room(float $pHeight): void
    {
        if ($this->aY - $pHeight < self::MARGIN) {
            $this->aPages[] = $this->aContent;
            $this->aContent = '';
            $this->aY = self::PAGE_HEIGHT - self::MARGIN;
        }
    }

    /**
     * Coupe au mot près ; la largeur d'un caractère est estimée à la moitié de la taille, avec un peu plus pour le gras.
     *
     * @return list<string>
     */
    private function wrap(string $pText, float $pSize, bool $pBold, float $pWidth): array
    {
        $vMax = max(1, (int) floor($pWidth / ($pSize * ($pBold ? 0.56 : 0.52))));
        $rLines = [];
        $vLine = '';
        foreach (preg_split('/\s+/u', trim($pText)) ?: [] as $vWord) {
            $vWord = mb_substr($vWord, 0, $vMax);
            $vTry = '' === $vLine ? $vWord : $vLine.' '.$vWord;
            if (mb_strlen($vTry) > $vMax) {
                $rLines[] = $vLine;
                $vLine = $vWord;
            } else {
                $vLine = $vTry;
            }
        }
        $rLines[] = $vLine;

        return $rLines;
    }

    /** Texte en Windows-1252 (les accents français), avec échappement des caractères propres au format PDF. */
    private function escape(string $pText): string
    {
        $vText = iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $pText);

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], false === $vText ? '' : $vText);
    }

    private function number(float $pValue): string
    {
        return rtrim(rtrim(\sprintf('%.2F', $pValue), '0'), '.');
    }
}
