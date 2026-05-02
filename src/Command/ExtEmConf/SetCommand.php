<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf;

use Packwright\Console\Concern\Typo3VersionOption;
use Packwright\Console\DisplayPathFormatter;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\State;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Result\Issue;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
use Packwright\ExtEmConf\Service\ExtEmConfValidator;
use Packwright\ExtEmConf\Service\ExtEmConfWriter;
use Packwright\ExtEmConf\Service\PathResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'extemconf:set',
    description: 'Set simple scalar fields on one or more `ext_emconf.php` files',
)]
final class SetCommand extends Command
{
    use Typo3VersionOption;

    /**
     * Per-field option name → DTO property name. The CLI option for
     * `version` is renamed to `ext-version` to avoid colliding with
     * Symfony's built-in application-level `--version` option.
     */
    private const STRING_FIELDS = [
        'title' => 'title',
        'description' => 'description',
        'ext-version' => 'version',
        'author' => 'author',
        'author-email' => 'authorEmail',
        'author-company' => 'authorCompany',
    ];

    public function __construct(
        private readonly PathResolver $pathResolver,
        private readonly ExtEmConfReader $reader,
        private readonly ExtEmConfValidator $validator,
        private readonly ExtEmConfWriter $writer,
        private readonly DisplayPathFormatter $displayPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'paths',
                mode: InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                description: 'File paths, directories, or glob patterns pointing to ext_emconf.php files.',
            );

        foreach (self::STRING_FIELDS as $option => $property) {
            $this->addOption(
                name: $option,
                mode: InputOption::VALUE_REQUIRED,
                description: sprintf('Set the `%s` field.', $this->fieldKey($property)),
            );
        }

        $this
            ->addOption(
                name: 'state',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Set the `state` field. Allowed: ' . implode(', ', $this->stateValues()) . '.',
            )
            ->addOption(
                name: 'category',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Set the `category` field. Allowed: ' . implode(', ', $this->categoryValues()) . '.',
            )
            ->addOption(
                name: 'strict',
                mode: InputOption::VALUE_NONE,
                description: 'Require validation state SUCCESS to write; without `--strict` WARNING is also allowed.',
            )
            ->addOption(
                name: 'dry-run',
                mode: InputOption::VALUE_NONE,
                description: 'Do not write files; only report what would change.',
            )
            ->addOption(
                name: 'json',
                mode: InputOption::VALUE_NONE,
                description: 'Output a JSON document instead of a table.',
            );

        $this->configureTypo3VersionOption('validate against');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $version = $this->parseTypo3VersionOption($input);
        if ($version === false) {
            $stderr->writeln(sprintf(
                '<error>Invalid TYPO3 version "%s". Allowed: 8, 9, 10, 11, 12, 13.</error>',
                (string)$input->getOption('typo3-version'),
            ));

            return Command::INVALID;
        }

        $stringOverrides = $this->collectStringOverrides($input);
        $stateOverride = $this->resolveStateOverride($input, $stderr);
        if ($stateOverride === false) {
            return Command::INVALID;
        }
        $categoryOverride = $this->resolveCategoryOverride($input, $stderr);
        if ($categoryOverride === false) {
            return Command::INVALID;
        }

        $hasOverride = $stringOverrides !== []
            || $stateOverride !== null
            || $categoryOverride !== null;
        if (!$hasOverride) {
            $stderr->writeln('<error>No field given. Pass at least one --<field>=value option.</error>');

            return Command::INVALID;
        }

        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');

        try {
            $files = $this->pathResolver->resolve($paths);
        } catch (\InvalidArgumentException $exception) {
            $stderr->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        if ($files === []) {
            $stderr->writeln('<error>No files matched the given paths/patterns.</error>');

            return Command::INVALID;
        }

        $strict = (bool)$input->getOption('strict');
        $dryRun = (bool)$input->getOption('dry-run');

        $rows = [];
        $exitCode = Command::SUCCESS;
        foreach ($files as $file) {
            $row = $this->processFile(
                file: $file,
                stringOverrides: $stringOverrides,
                stateOverride: $stateOverride,
                categoryOverride: $categoryOverride,
                version: $version,
                strict: $strict,
                dryRun: $dryRun,
            );
            $rows[] = $row;
            if ($row['state'] === ResultState::FAILURE) {
                $exitCode = Command::FAILURE;
            }
        }

        if ($input->getOption('json')) {
            $output->writeln($this->renderJson($rows, $version, $dryRun, $strict));

            return $exitCode;
        }

        $this->renderTable($rows, $version, $output);

        return $exitCode;
    }

    /**
     * @return array<string, string>
     */
    private function collectStringOverrides(InputInterface $input): array
    {
        $overrides = [];
        foreach (self::STRING_FIELDS as $option => $property) {
            $value = $input->getOption($option);
            if ($value === null) {
                continue;
            }
            $overrides[$property] = (string)$value;
        }

        return $overrides;
    }

    private function resolveStateOverride(InputInterface $input, OutputInterface $stderr): false|State|null
    {
        $value = $input->getOption('state');
        if ($value === null) {
            return null;
        }
        $resolved = State::tryFrom((string)$value);
        if ($resolved === null) {
            $stderr->writeln(sprintf(
                '<error>Invalid state "%s". Allowed: %s.</error>',
                (string)$value,
                implode(', ', $this->stateValues()),
            ));

            return false;
        }

        return $resolved;
    }

    private function resolveCategoryOverride(InputInterface $input, OutputInterface $stderr): false|Category|null
    {
        $value = $input->getOption('category');
        if ($value === null) {
            return null;
        }
        $resolved = Category::tryFrom((string)$value);
        if ($resolved === null) {
            $stderr->writeln(sprintf(
                '<error>Invalid category "%s". Allowed: %s.</error>',
                (string)$value,
                implode(', ', $this->categoryValues()),
            ));

            return false;
        }

        return $resolved;
    }

    /**
     * @param array<string, string> $stringOverrides
     *
     * @return array{
     *     file: string,
     *     state: ResultState,
     *     issues: list<Issue>,
     *     action: Action,
     *     generated: ?string,
     * }
     */
    private function processFile(
        string $file,
        array $stringOverrides,
        ?State $stateOverride,
        ?Category $categoryOverride,
        ?Typo3Version $version,
        bool $strict,
        bool $dryRun,
    ): array {
        $readResult = $this->reader->read($file);
        if ($readResult->extEmConf === null) {
            return [
                'file' => $file,
                'state' => ResultState::FAILURE,
                'issues' => $readResult->issues->all(),
                'action' => Action::SKIPPED,
                'generated' => null,
            ];
        }

        $mutated = $this->applyOverrides(
            original: $readResult->extEmConf,
            stringOverrides: $stringOverrides,
            stateOverride: $stateOverride,
            categoryOverride: $categoryOverride,
        );

        $validation = $this->validator->validate($mutated, $version);
        $blocking = $validation->state === ResultState::FAILURE
            || ($strict && $validation->state !== ResultState::SUCCESS);
        if ($blocking) {
            return [
                'file' => $file,
                'state' => $validation->state,
                'issues' => $validation->issues->all(),
                'action' => Action::SKIPPED,
                'generated' => null,
            ];
        }

        $generated = $this->writer->generate($mutated, $version);
        $existing = (string)file_get_contents($file);
        if ($existing === $generated) {
            return [
                'file' => $file,
                'state' => $validation->state,
                'issues' => $validation->issues->all(),
                'action' => Action::UNCHANGED,
                'generated' => $generated,
            ];
        }

        if ($dryRun) {
            return [
                'file' => $file,
                'state' => $validation->state,
                'issues' => $validation->issues->all(),
                'action' => Action::DRY_RUN,
                'generated' => $generated,
            ];
        }

        file_put_contents($file, $generated, LOCK_EX);

        return [
            'file' => $file,
            'state' => $validation->state,
            'issues' => $validation->issues->all(),
            'action' => Action::WRITTEN,
            'generated' => $generated,
        ];
    }

    /**
     * @param array<string, string> $stringOverrides
     */
    private function applyOverrides(
        ExtEmConf $original,
        array $stringOverrides,
        ?State $stateOverride,
        ?Category $categoryOverride,
    ): ExtEmConf {
        return new ExtEmConf(
            title: $stringOverrides['title'] ?? $original->title,
            description: $stringOverrides['description'] ?? $original->description,
            version: $stringOverrides['version'] ?? $original->version,
            category: $categoryOverride ?? $original->category,
            state: $stateOverride ?? $original->state,
            author: $stringOverrides['author'] ?? $original->author,
            authorEmail: $stringOverrides['authorEmail'] ?? $original->authorEmail,
            authorCompany: $stringOverrides['authorCompany'] ?? $original->authorCompany,
            constraints: $original->constraints,
            autoload: $original->autoload,
            autoloadDev: $original->autoloadDev,
            clearCacheOnLoad: $original->clearCacheOnLoad,
            uploadfolder: $original->uploadfolder,
            createDirs: $original->createDirs,
            unknownProperties: $original->unknownProperties,
        );
    }

    /**
     * @param list<array{file: string, state: ResultState, issues: list<Issue>, action: Action, generated: ?string}> $rows
     */
    private function renderTable(array $rows, ?Typo3Version $version, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['File', 'TYPO3', 'State', 'Issues', 'Action']);

        $versionLabel = $version?->label() ?? 'any';

        foreach ($rows as $row) {
            $table->addRow([
                $this->displayPath->format($row['file']),
                $versionLabel,
                $this->stateLabel($row['state']),
                (string)count($row['issues']),
                $row['action']->label(),
            ]);
        }

        $table->render();

        if ($output->isVerbose()) {
            $this->renderIssueDetails($rows, $output);

            return;
        }

        foreach ($rows as $row) {
            if ($row['issues'] !== []) {
                $output->writeln('');
                $output->writeln('<info>Tip: re-run with -v to see per-issue details.</info>');

                return;
            }
        }
    }

    /**
     * @param list<array{file: string, state: ResultState, issues: list<Issue>, action: Action, generated: ?string}> $rows
     */
    private function renderIssueDetails(array $rows, OutputInterface $output): void
    {
        foreach ($rows as $row) {
            if ($row['issues'] === []) {
                continue;
            }
            $output->writeln('');
            $output->writeln(sprintf('<comment>%s</comment>', $this->displayPath->format($row['file'])));
            foreach ($row['issues'] as $issue) {
                $output->writeln(sprintf(
                    '  %s  %s  %s',
                    $issue->severity->name,
                    $issue->path === '' ? '-' : $issue->path,
                    $issue->message,
                ));
            }
        }
    }

    /**
     * @param list<array{file: string, state: ResultState, issues: list<Issue>, action: Action, generated: ?string}> $rows
     */
    private function renderJson(array $rows, ?Typo3Version $version, bool $dryRun, bool $strict): string
    {
        $files = [];
        $summary = [
            'total' => count($rows),
            'written' => 0,
            'dryRun' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failure' => 0,
        ];

        foreach ($rows as $row) {
            $files[] = [
                'path' => $row['file'],
                'state' => $row['state']->name,
                'action' => $row['action']->label(),
                'issues' => array_map(
                    static fn(Issue $i): array => [
                        'severity' => $i->severity->name,
                        'path' => $i->path,
                        'message' => $i->message,
                    ],
                    $row['issues'],
                ),
                'generated' => $row['generated'],
            ];

            $summary[$this->summaryKey($row['action'])]++;
            if ($row['state'] === ResultState::FAILURE) {
                $summary['failure']++;
            }
        }

        $payload = [
            'command' => 'extemconf:set',
            'typo3Version' => $version?->value,
            'strict' => $strict,
            'dryRun' => $dryRun,
            'files' => $files,
            'summary' => $summary,
        ];

        return (string)json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    private function summaryKey(Action $action): string
    {
        return match ($action) {
            Action::WRITTEN => 'written',
            Action::DRY_RUN => 'dryRun',
            Action::UNCHANGED => 'unchanged',
            Action::SKIPPED => 'skipped',
        };
    }

    private function stateLabel(ResultState $state): string
    {
        return match ($state) {
            ResultState::SUCCESS => '<info>SUCCESS</info>',
            ResultState::WARNING => '<comment>WARNING</comment>',
            ResultState::FAILURE => '<error>FAILURE</error>',
        };
    }

    /**
     * @return list<string>
     */
    private function stateValues(): array
    {
        return array_map(static fn(State $s): string => $s->value, State::cases());
    }

    /**
     * @return list<string>
     */
    private function categoryValues(): array
    {
        return array_map(static fn(Category $c): string => $c->value, Category::cases());
    }

    private function fieldKey(string $property): string
    {
        return match ($property) {
            'authorEmail' => 'author_email',
            'authorCompany' => 'author_company',
            default => $property,
        };
    }
}
