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
    private const MAX_ID_LENGTH = 100;

    public function __construct(
        private IAppDataFactory $appDataFactory,
    ) { }

    /**
     * @return array<string, mixed>
     */
    public function load(TrainVersion $version): array
    {
        $folder = $this->appDataFactory->get(Application::APP_ID);
        $fileName = $this->fileName($version);

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

    public function exists(TrainVersion $version): bool
    {
        $folder = $this->appDataFactory->get(Application::APP_ID);

        try {
            return $folder->getFolder('content')->fileExists($this->fileName($version));
        } catch (NotFoundException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyContent(): array
    {
        return [
            'schemaVersion' => self::SUPPORTED_SCHEMA_VERSION,
            'meta' => ['language' => 'de'],
            'pages' => [
                ['id' => 'page-' . bin2hex(random_bytes(3)), 'title' => 'Neue Seite', 'sections' => []],
            ],
        ];
    }

    /**
     * Writes the content of a version to the same place load() reads it from.
     * The caller has to validate() first. HTML is stored as given, it is deliberately not filtered.
     *
     * @param array<string, mixed> $content
     */
    public function save(TrainVersion $version, array $content): void
    {
        $folder = $this->appDataFactory->get(Application::APP_ID);
        $fileName = $this->fileName($version);
        $json = json_encode(
            $content,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        try {
            $contentFolder = $folder->getFolder('content');
        } catch (NotFoundException) {
            $contentFolder = $folder->newFolder('content');
        }

        if ($contentFolder->fileExists($fileName)) {
            $contentFolder->getFile($fileName)->putContent($json);
        } else {
            $contentFolder->newFile($fileName, $json);
        }
    }

    /**
     * Checks structure and consistency of the content (not the HTML in it).
     * IDs of pages, sections and questions must be unique within the whole content,
     * because progress and evaluation (indexQuestions() etc.) refer to them.
     *
     * @return list<string> human readable errors, empty if valid
     */
    public function validate(mixed $content): array
    {
        if (!is_array($content)) {
            return ['Der Inhalt hat ein ungültiges Format.'];
        }

        $errors = [];

        if (($content['schemaVersion'] ?? null) !== self::SUPPORTED_SCHEMA_VERSION) {
            $errors[] = sprintf('schemaVersion muss %d sein.', self::SUPPORTED_SCHEMA_VERSION);
        }

        $meta = $content['meta'] ?? null;
        if (!is_array($meta) || !$this->isFilledString($meta['language'] ?? null)) {
            $errors[] = 'Allgemein: Die Sprache fehlt.';
        } elseif (isset($meta['estimatedMinutes']) && (!is_int($meta['estimatedMinutes']) || $meta['estimatedMinutes'] < 1)) {
            $errors[] = 'Allgemein: Die geschätzte Dauer muss eine ganze Zahl ab 1 sein.';
        }

        $pages = $content['pages'] ?? null;
        if (!is_array($pages) || $pages === [] || !array_is_list($pages)) {
            $errors[] = 'Es muss mindestens eine Seite geben.';
            return $errors;
        }

        $seen = ['page' => [], 'section' => [], 'question' => []];

        foreach ($pages as $pageIndex => $page) {
            $pageLabel = sprintf('Seite %d', $pageIndex + 1);

            if (!is_array($page)) {
                $errors[] = $pageLabel . ': ungültiges Format.';
                continue;
            }

            $this->checkId($page['id'] ?? null, $pageLabel, $seen['page'], $errors);

            if (!$this->isFilledString($page['title'] ?? null)) {
                $errors[] = $pageLabel . ': Der Titel fehlt.';
            }

            $sections = $page['sections'] ?? null;
            if (!is_array($sections) || !array_is_list($sections)) {
                $errors[] = $pageLabel . ': Die Abschnitte haben ein ungültiges Format.';
                continue;
            }

            foreach ($sections as $sectionIndex => $section) {
                $label = sprintf('%s, Abschnitt %d', $pageLabel, $sectionIndex + 1);
                $this->validateSection($section, $label, $seen, $errors);
            }
        }

        return $errors;
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

    private function fileName(TrainVersion $version): string
    {
        return sprintf('%d_%d.json', $version->getTrainId(), $version->getVersionNumber());
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

    /**
     * @param array<string, array<string, true>> $seen
     * @param list<string> $errors
     */
    private function validateSection(mixed $section, string $label, array &$seen, array &$errors): void
    {
        if (!is_array($section)) {
            $errors[] = $label . ': ungültiges Format.';
            return;
        }

        $this->checkId($section['id'] ?? null, $label, $seen['section'], $errors);

        switch ($section['type'] ?? null) {
            case 'content':
                $columns = $section['columns'] ?? null;
                $valid = is_array($columns) && array_is_list($columns)
                    && count($columns) >= 1 && count($columns) <= 2
                    && count(array_filter($columns, 'is_string')) === count($columns);
                if (!$valid) {
                    $errors[] = $label . ': Ein Inhaltsabschnitt braucht eine oder zwei Spalten.';
                }
                break;

            case 'quiz':
                $this->validateQuiz($section, $label, $seen, $errors);
                break;

            default:
                $errors[] = $label . ': Unbekannter Abschnittstyp.';
        }
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, array<string, true>> $seen
     * @param list<string> $errors
     */
    private function validateQuiz(array $section, string $label, array &$seen, array &$errors): void
    {
        if (!is_bool($section['scored'] ?? null)) {
            $errors[] = $label . ': "Zählt für das Bestehen" ist nicht gesetzt.';
        }

        $questions = $section['questions'] ?? null;
        if (!is_array($questions) || $questions === [] || !array_is_list($questions)) {
            $errors[] = $label . ': Ein Quiz braucht mindestens eine Frage.';
            return;
        }

        foreach ($questions as $questionIndex => $question) {
            $questionLabel = sprintf('%s, Frage %d', $label, $questionIndex + 1);

            if (!is_array($question)) {
                $errors[] = $questionLabel . ': ungültiges Format.';
                continue;
            }

            $this->checkId($question['id'] ?? null, $questionLabel, $seen['question'], $errors);

            $type = $question['type'] ?? null;
            if (!in_array($type, ['single', 'multiple'], true)) {
                $errors[] = $questionLabel . ': Unbekannter Fragetyp.';
            }

            if (!$this->isFilledString($question['text'] ?? null)) {
                $errors[] = $questionLabel . ': Der Fragetext fehlt.';
            }

            if (isset($question['points']) && (!is_int($question['points']) || $question['points'] < 1)) {
                $errors[] = $questionLabel . ': Die Punkte müssen eine ganze Zahl ab 1 sein.';
            }

            $options = $question['options'] ?? null;
            if (!is_array($options) || count($options) < 2 || !array_is_list($options)) {
                $errors[] = $questionLabel . ': Mindestens zwei Antworten sind nötig.';
                continue;
            }

            $optionIds = [];
            $correct = 0;
            foreach ($options as $optionIndex => $option) {
                $optionLabel = sprintf('%s, Antwort %d', $questionLabel, $optionIndex + 1);

                if (!is_array($option)) {
                    $errors[] = $optionLabel . ': ungültiges Format.';
                    continue;
                }

                $this->checkId($option['id'] ?? null, $optionLabel, $optionIds, $errors);

                if (!$this->isFilledString($option['text'] ?? null)) {
                    $errors[] = $optionLabel . ': Der Text fehlt.';
                }
                if (!is_bool($option['correct'] ?? null)) {
                    $errors[] = $optionLabel . ': "richtig" ist nicht gesetzt.';
                } elseif ($option['correct']) {
                    $correct++;
                }
            }

            if ($type === 'single' && $correct !== 1) {
                $errors[] = $questionLabel . ': Bei einer Einzelauswahl muss genau eine Antwort richtig sein.';
            } elseif ($type === 'multiple' && $correct < 1) {
                $errors[] = $questionLabel . ': Mindestens eine Antwort muss richtig sein.';
            }
        }
    }

    /**
     * @param array<string, true> $seen
     * @param list<string> $errors
     */
    private function checkId(mixed $id, string $label, array &$seen, array &$errors): void
    {
        if (!$this->isFilledString($id) || mb_strlen($id) > self::MAX_ID_LENGTH) {
            $errors[] = $label . ': Die ID fehlt oder ist zu lang.';
            return;
        }

        if (isset($seen[$id])) {
            $errors[] = sprintf('%s: Die ID "%s" kommt mehrfach vor.', $label, $id);
            return;
        }

        $seen[$id] = true;
    }

    private function isFilledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}