<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use OCA\SfxonTrain\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;

class PdfCertificateService
{
    private const CERTIFICATE_FOLDER = 'certificates';

    public function __construct(
        private IAppDataFactory $appDataFactory,
    ) {
    }

    /**
     * @param array{
     *   participantName: string,
     *   trainingTitle: string,
     *   completedAt: \DateTimeInterface,
     *   scorePercent: int|null,
     *   versionNumber: int|null,
     *   validUntil?: \DateTimeInterface|null
     * } $data
     *
     * "validUntil" weglassen = Spalte "G�ltig bis" ausblenden;
     * null = "unbefristet".
     */
    public function getOrCreate(
        string $userId,
        int $trainingId,
        int $versionId,
        array $data,
    ): string {
        $folder = $this->certificateFolder();
        $filename = $this->filename($userId, $trainingId, $versionId);

        /*
        try {
            return $folder->getFile($filename)->getContent();
        } catch (NotFoundException) {
            // Generate it once; subsequent requests reuse the stored PDF.
        }
        */

        $html = $this->renderTemplate($data);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = $dompdf->output();
        $folder->newFile($filename)->putContent($pdf);

        return $pdf;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTemplate(array $data): string
    {
        $template = dirname(__DIR__, 2) . '/templates/certificate.php';

        if (!is_file($template)) {
            throw new \RuntimeException('Zertifikat-Template nicht gefunden: ' . $template);
        }

        $escape = static fn (mixed $value): string => htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $participantName = $escape($data['participantName'] ?? '');
        $trainingTitle = $escape($data['trainingTitle'] ?? '');
        $completedAt = $data['completedAt'] instanceof \DateTimeInterface
            ? $escape($data['completedAt']->format('d.m.Y'))
            : '';
        $scorePercent = $data['scorePercent'] === null ? null : (int)$data['scorePercent'];
        $versionNumber = $data['versionNumber'] === null ? null : (int)$data['versionNumber'];

        $hasValidUntil = array_key_exists('validUntil', $data);
        $validUntil = ($data['validUntil'] ?? null) instanceof \DateTimeInterface
            ? $escape($data['validUntil']->format('d.m.Y'))
            : null;

        $logoTop = $this->imageDataUri('logo-top.png');
        $logoWatermark = $this->imageDataUri('logo-hintergrund.png');

        ob_start();
        require $template;
        return (string)ob_get_clean();
    }

    private function imageDataUri(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/img/' . $file;
        $bytes = @file_get_contents($path);

        if ($bytes === false) {
            throw new \RuntimeException('Zertifikat-Bild nicht gefunden: ' . $path);
        }

        return 'data:image/png;base64,' . base64_encode($bytes);
    }

    private function certificateFolder(): \OCP\Files\SimpleFS\ISimpleFolder
    {
        $appData = $this->appDataFactory->get(Application::APP_ID);

        try {
            return $appData->getFolder(self::CERTIFICATE_FOLDER);
        } catch (NotFoundException) {
            return $appData->newFolder(self::CERTIFICATE_FOLDER);
        }
    }

    private function filename(string $userId, int $trainingId, int $versionId): string
    {
        // Keep the requested user/training/version identifiers while preventing path separators.
        $safeUserId = preg_replace('/[^A-Za-z0-9._-]/', '_', $userId) ?: 'user';
        return sprintf('%s_%d_%d.pdf', $safeUserId, $trainingId, $versionId);
    }
}
