<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf\Autoload\Psr4;

use Packwright\Command\ExtEmConf\Action;
use Packwright\Console\Concern\Typo3VersionOption;
use Packwright\Console\DisplayPathFormatter;
use Packwright\ExtEmConf\Dto\Autoload;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Collection;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Entry;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Enum\IssueSeverity;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Result\Issue;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
use Packwright\ExtEmConf\Service\ExtEmConfValidator;
use Packwright\ExtEmConf\Service\ExtEmConfWriter;
use Packwright\ExtEmConf\Service\PathResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'extemconf:autoload:psr4:set',
    description: 'Add or update a `autoload.psr-4` namespace mapping',
)]
final class SetCommand extends Command
{
    use Typo3VersionOption;

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
                name: 'namespace',
                mode: InputArgument::REQUIRED,
                description: 'PSR-4 namespace prefix (must end with `\\\\`).',
            )
            ->addArgument(
                name: 'path',
                mode: InputArgument::REQUIRED,
                description: 'Path the namespace prefix maps to (relative to the extension root).',
            )
            ->addArgument(
                name: 'paths',
                mode: InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                description: 'File paths, directories, or glob patterns pointing to ext_emconf.php files.',
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
                description: 'Output a JSON document instead of a table. Forces non-interactive mode.',
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

        $namespace = (string)$input->getArgument('namespace');
        if (!$this->isValidPsr4Namespace($namespace)) {
            $stderr->writeln(sprintf(
                '<error>Invalid PSR-4 namespace "%s". Must be non-empty and end with `\\`.</error>',
                $namespace,
            ));

            return Command::INVALID;
        }

        $path = trim((string)$input->getArgument('path'));
        if ($path === '') {
            $stderr->writeln('<error>Path argument must not be empty.</error>');

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
        $json = (bool)$input->getOption('json');

        $rows = [];
        $exitCode = Command::SUCCESS;
        foreach ($files as $file) {
            $row = $this->processFile(
                file: $file,
                namespace: $namespace,
                path: $path,
                version: $version,
                strict: $strict,
                dryRun: $dryRun,
                input: $input,
                output: $output,
                json: $json,
            );
            $rows[] = $row;
            if ($row['state'] === ResultState::FAILURE) {
                $exitCode = Command::FAILURE;
            }
        }

        if ($json) {
            $output->writeln($this->renderJson($rows, $namespace, $path, $version, $dryRun, $strict));

            return $exitCode;
        }

        $this->renderTable($rows, $version, $output);

        return $exitCode;
    }

    /**
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
        string $namespace,
        string $path,
        ?Typo3Version $version,
        bool $strict,
        bool $dryRun,
        InputInterface $input,
        OutputInterface $output,
        bool $json,
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

        $original = $readResult->extEmConf;
        $existingDevEntry = $original->autoloadDev->psr4->get($namespace);

        $move = false;
        if ($existingDevEntry !== null) {
            $allowPrompt = $input->isInteractive() && !$json;
            if (!$allowPrompt) {
                return [
                    'file' => $file,
                    'state' => ResultState::FAILURE,
                    'issues' => [new Issue(
                        path: sprintf('autoload-dev.psr-4.%s', $namespace),
                        message: sprintf(
                            'Namespace "%s" is already present under `autoload-dev`; '
                            . 'rerun interactively to move it, or unset it there first.',
                            $namespace,
                        ),
                        severity: IssueSeverity::ERROR,
                    )],
                    'action' => Action::SKIPPED,
                    'generated' => null,
                ];
            }

            $oldPath = $existingDevEntry->path;
            $helper = $this->getHelper('question');
            assert($helper instanceof QuestionHelper);
            $question = new ConfirmationQuestion(
                sprintf(
                    "Namespace '%s' already exists in `autoload-dev` (path '%s').\n"
                    . "Move it to `autoload` with path '%s'? [y/N] ",
                    $namespace,
                    $oldPath,
                    $path,
                ),
                false,
            );
            $move = (bool)$helper->ask($input, $output, $question);
            if (!$move) {
                return [
                    'file' => $file,
                    'state' => ResultState::FAILURE,
                    'issues' => [new Issue(
                        path: sprintf('autoload-dev.psr-4.%s', $namespace),
                        message: sprintf(
                            'Namespace "%s" is already present under `autoload-dev`; '
                            . 'move declined, no changes written.',
                            $namespace,
                        ),
                        severity: IssueSeverity::ERROR,
                    )],
                    'action' => Action::SKIPPED,
                    'generated' => null,
                ];
            }
        }

        $mutated = $this->withPsr4Entry($original, $namespace, $path, $move);
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
        $existingContent = (string)file_get_contents($file);
        if ($existingContent === $generated) {
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

    private function withPsr4Entry(
        ExtEmConf $original,
        string $namespace,
        string $path,
        bool $removeFromDev,
    ): ExtEmConf {
        $autoloadEntries = [];
        foreach ($original->autoload->psr4->all() as $entry) {
            if ($entry->namespace !== $namespace) {
                $autoloadEntries[] = $entry;
            }
        }
        $autoloadEntries[] = new Psr4Entry(namespace: $namespace, path: $path);

        $autoloadDevEntries = [];
        foreach ($original->autoloadDev->psr4->all() as $entry) {
            if ($removeFromDev && $entry->namespace === $namespace) {
                continue;
            }
            $autoloadDevEntries[] = $entry;
        }

        $newAutoload = new Autoload(
            psr4: new Psr4Collection($autoloadEntries),
            classmap: $original->autoload->classmap,
        );
        $newAutoloadDev = new Autoload(
            psr4: new Psr4Collection($autoloadDevEntries),
            classmap: $original->autoloadDev->classmap,
        );

        return new ExtEmConf(
            title: $original->title,
            description: $original->description,
            version: $original->version,
            category: $original->category,
            state: $original->state,
            author: $original->author,
            authorEmail: $original->authorEmail,
            authorCompany: $original->authorCompany,
            constraints: $original->constraints,
            autoload: $newAutoload,
            autoloadDev: $newAutoloadDev,
            clearCacheOnLoad: $original->clearCacheOnLoad,
            uploadfolder: $original->uploadfolder,
            createDirs: $original->createDirs,
            unknownProperties: $original->unknownProperties,
        );
    }

    private function isValidPsr4Namespace(string $namespace): bool
    {
        return $namespace !== '' && str_ends_with($namespace, '\\');
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
    private function renderJson(
        array $rows,
        string $namespace,
        string $path,
        ?Typo3Version $version,
        bool $dryRun,
        bool $strict,
    ): string {
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
            'command' => 'extemconf:autoload:psr4:set',
            'target' => 'autoload',
            'namespace' => $namespace,
            'path' => $path,
            'typo3Version' => $version?->value,
            'strict' => $strict,
            'dryRun' => $dryRun,
            'files' => $files,
            'summary' => $summary,
        ];

        return (string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
}
