<?php

declare(strict_types=1);

namespace OCA\SfxonTrain\Service;

use OCA\SfxonTrain\AppInfo\Application;
use OCA\SfxonTrain\Db\TrainVersion;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;

class TrainContentService
{
    private const SUPPORTED_SCHEMA_VERSION = 1;

    public function __construct(
        private IAppDataFactory $appDataFactory,
    ) { }

    /**
     * @return array<string, mixed>
     */
    public function load(TrainVersion $version): array
    {
        $folder = $this->appDataFactory->get(Application::APP_ID);
        $fileName = sprintf('%d_%d.json', $version->getTrainId(), $version->getVersionNumber());

        try {
            $raw = $folder->getFolder('content')->getFile($fileName)->getContent();
        } catch (NotFoundException $e) {
            throw new \RuntimeException(sprintf('Kein Inhalt gefunden: appdata/%s/content/%s', Application::APP_ID, $fileName), 0, $e);
        }

        $content = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (($content['schemaVersion'] ?? null) !== self::SUPPORTED_SCHEMA_VERSION) {
            throw new \RuntimeException(sprintf('Nicht unterstützte schemaVersion in Version %d.', $version->getId()));
        }
        if (empty($content['pages']) || !is_array($content['pages'])) {
            throw new \RuntimeException(sprintf('Version %d enthält keine Seiten.', $version->getId()));
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public function toClient(array $content): array
    {
        $content['pages'] = array_map(
            fn (array $page): array => [
                ...$page,
                'sections' => array_map($this->sanitizeSection(...), $page['sections'] ?? []),
            ],
            $content['pages'],
        );

        return $content;
    }

    /**
     * @param array<string, mixed> $content
     * @return array<string, array{question: array<string, mixed>, scored: bool}>
     */
    public function indexQuestions(array $content): array
    {
        $index = [];

        foreach ($content['pages'] as $page) {
            foreach ($page['sections'] ?? [] as $section) {
                if (($section['type'] ?? '') !== 'quiz') {
                    continue;
                }
                foreach ($section['questions'] ?? [] as $question) {
                    $index[$question['id']] = [
                        'question' => $question,
                        'scored' => (bool) ($section['scored'] ?? false),
                    ];
                }
            }
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $content
     * @return list<string>
     */
    public function pageIds(array $content): array
    {
        return array_values(array_map(static fn (array $page): string => (string) $page['id'], $content['pages']));
    }

    /**
     * @param array<string, mixed> $section
     * @return array<string, mixed>
     */
    private function sanitizeSection(array $section): array
    {
        if (($section['type'] ?? '') !== 'quiz') {
            return $section;
        }

        $section['questions'] = array_map(static function (array $question): array {
            unset($question['explanation']);
            $question['options'] = array_map(static function (array $option): array {
                unset($option['correct']);
                return $option;
            }, $question['options'] ?? []);

            return $question;
        }, $section['questions'] ?? []);

        return $section;
    }
}