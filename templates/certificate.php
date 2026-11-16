<?php
/**
 * Zertifikat (A4 hochkant) für Dompdf.
 *
 * Erwartete Variablen (Texte bereits HTML-escaped):
 *   string      $participantName
 *   string      $trainingTitle
 *   string      $completedAt
 *   int|null    $scorePercent
 *   int|null    $versionNumber
 *   bool        $hasValidUntil  (false = Spalte "Gültig bis" ausblenden)
 *   string|null $validUntil     (null bei $hasValidUntil = true bedeutet "unbefristet")
 *   string      $logoTop        (data-URI)
 *   string      $logoWatermark  (data-URI)
 */

// Kennzahlen-Leiste: nur anzeigen, was vorhanden ist.
$facts = [['Abgeschlossen am', $completedAt]];
if ($scorePercent !== null) {
    $facts[] = ['Ergebnis', $scorePercent . ' %'];
}
if ($hasValidUntil) {
    $facts[] = ['Gültig bis', $validUntil ?? 'unbefristet'];
}
$factWidth = number_format(100 / count($facts), 2, '.', '');

// Lange Namen etwas verkleinern, damit sie nicht umbrechen.
$nameLength = mb_strlen(html_entity_decode($participantName, ENT_QUOTES, 'UTF-8'));
$nameSize = $nameLength > 32 ? 16 : ($nameLength > 22 ? 22 : 28);
$nameLineWidth = $nameLength > 22 ? 150 : 110;

$titleLength = mb_strlen(html_entity_decode($trainingTitle, ENT_QUOTES, 'UTF-8'));
$courseSize = $titleLength > 60 ? 14 : ($titleLength > 40 ? 16 : 19);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 portrait; margin: 0; }

        html, body { margin: 0; padding: 0; }
        body { font-family: "DejaVu Sans", sans-serif; color: #1f2937; }

        /* Komplette Seite: feste Maße, alles darin absolut positioniert.
           Höhe knapp unter 297 mm, damit Dompdf keine zweite Seite anlegt. */
        .page { position: absolute; left: 0; top: 0; width: 210mm; height: 296mm; }

        .frame-outer { position: absolute; left: 8mm; top: 8mm; width: 194mm; height: 280mm;
                       border: 1.2mm solid #00254a; }
        .frame-inner { position: absolute; left: 12mm; top: 12mm; width: 185.6mm; height: 272.6mm;
                       border: 0.3mm solid #b8975a; }

        .watermark { position: absolute; left: 60mm; top: 113mm; width: 90mm; }
        .watermark img { width: 90mm; }

        .logo { position: absolute; left: 0; top: 28mm; width: 210mm; text-align: center; }
        .logo img { width: 55mm; }

        .content { position: absolute; left: 25mm; top: 62mm; width: 160mm; text-align: center; }

        .eyebrow { font-size: 9pt; letter-spacing: 3pt; color: #b8975a; text-transform: uppercase; }
        .title { font-family: "DejaVu Serif", serif; font-size: 36pt; letter-spacing: 5pt;
                 color: #00254a; margin: 5mm 0 0 0; text-transform: uppercase; }
        .divider { width: 40mm; height: 0; border-top: 0.6mm solid #b8975a; margin: 7mm auto 14mm auto; }

        .lead { font-size: 12pt; color: #6b7280; margin: 0 0 8mm 0; }
        .name { font-family: "DejaVu Serif", serif; color: #00254a; margin: 0; padding: 0 0 3mm 0; }
        .name-line { height: 0; border-top: 0.3mm solid #b8975a; margin: 0 auto 14mm auto; }

        .course-lead { font-size: 12pt; color: #6b7280; margin: 0 0 6mm 0; }
        .course { font-weight: bold; color: #1f2937; margin: 0 0 6mm 0; }
        .done { font-size: 12pt; color: #6b7280; margin: 0; }

        .facts-wrap { position: absolute; left: 25mm; top: 218mm; width: 160mm; }
        .facts { width: 160mm; border-collapse: collapse; }
        .facts td { text-align: center; padding: 0 2mm; vertical-align: top; }
        .facts td.sep { border-left: 0.3mm solid #b8975a; }
        .facts .label { font-size: 7.5pt; letter-spacing: 1.2pt; text-transform: uppercase; color: #6b7280; }
        .facts .value { font-size: 13pt; font-weight: bold; color: #00254a; padding-top: 2mm; }

        .footer-wrap { position: absolute; left: 20mm; top: 270mm; width: 170mm; }
        .footer { width: 170mm; font-size: 7pt; color: #6b7280; border-collapse: collapse; }
        .footer td { padding: 0; }
    </style>
</head>
<body>
<div class="page">

    <div class="watermark"><img src="<?= $logoWatermark ?>"></div>

    <div class="frame-outer"></div>
    <div class="frame-inner"></div>

    <div class="logo"><img src="<?= $logoTop ?>"></div>

    <div class="content">
        <div class="eyebrow">Teilnahme- und Erfolgsnachweis</div>
        <div class="title">Zertifikat</div>
        <div class="divider"></div>

        <p class="lead">Hiermit wird bestätigt, dass</p>
        <p class="name" style="font-size: <?= $nameSize ?>pt;"><?= $participantName ?></p>
        <div class="name-line" style="width: <?= $nameLineWidth ?>mm;"></div>

        <p class="course-lead">die Schulung</p>
        <p class="course" style="font-size: <?= $courseSize ?>pt;"><?= $trainingTitle ?></p>
        <p class="done">erfolgreich abgeschlossen hat.</p>
    </div>

    <div class="facts-wrap">
        <table class="facts">
            <tr>
                <?php foreach ($facts as $i => $fact): ?>
                    <td class="<?= $i > 0 ? 'sep' : '' ?>" style="width: <?= $factWidth ?>%;">
                        <div class="label"><?= $fact[0] ?></div>
                        <div class="value"><?= $fact[1] ?></div>
                    </td>
                <?php endforeach; ?>
            </tr>
        </table>
    </div>

    <div class="footer-wrap">
        <table class="footer">
            <tr>
                <td style="text-align: left;">Jackel &amp; Collegen Steuerberatungsgesellschaft mbH</td>
                <td style="text-align: right;">
                    <?php if ($versionNumber !== null): ?>Trainingsversion <?= $versionNumber ?> &middot; <?php endif; ?>Dieses Dokument wurde automatisch erstellt.
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>