<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf\Constraints;

use Packwright\Command\ExtEmConf\Action;
use Packwright\Console\Concern\Typo3VersionOption;
use Packwright\Console\DisplayPathFormatter;
use Packwright\ExtEmConf\Dto\Constraints\Constraints;
use Packwright\ExtEmConf\Dto\Constraints\DependencyCollection;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Enum\ResultState;
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
    name: 'extemconf:constraints:unset',
    description: 'Remove an entry from `constraints.{depends|suggests|conflicts}`',
)]
final class UnsetCommand extends Command
{
    use Typo3VersionOption;

    private const ALLOWED_TYPES = ['depends', 'suggests', 'conflicts'];

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
                name: 'type',
                mode: InputArgument::REQUIRED,
                description: 'Constraint group: ' . implode(' | ', self::ALLOWED_TYPES) . '.',
            )
            ->addArgument(
                name: 'extension-key',
                mode: InputArgument::REQUIRED,
                description: 'TYPO3 extension key to remove (lowercase letters, digits, underscores).',
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

        $type = (string)$input->getArgument('type');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $stderr->writeln(sprintf(
                '<error>Invalid type "%s". Allowed: %s.</error>',
                $type,
                implode(', ', self::ALLOWED_TYPES),
            ));

            return Command::INVALID;
        }

        $extensionKey = (string)$input->getArgument('extension-key');
        if ($extensionKey === '' || preg_match('/^[a-z0-9_]+$/', $extensionKey) !== 1) {
            $stderr->writeln(sprintf(
                '<error>Invalid extension key "%s". Use lowercase letters, digits, and underscores only.</error>',
                $extensionKey,
            ));

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
                type: $type,
                extensionKey: $extensionKey,
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
            $output->writeln($this->renderJson($rows, $type, $extensionKey, $version, $dryRun, $strict));

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
        string $type,
        string $extensionKey,
        ?Typo3Version $version,
        bool $strict,
        bool $dryRun,
    ): array {
        $readResult = $this->reader->read($file);
        if ($readResult->extEmConf === null) {
            return [
                'file' => $file,
                'state' => ResultState::FAILURE,
                'issues' => $readResult->issues,
                'action' => Action::SKIPPED,
                'generated' => null,
            ];
        }

        $original = $readResult->extEmConf;
        $existing = $this->groupCollection($original->constraints, $type);

        $hasEntry = false;
        foreach ($existing as $extKey => $_dependency) {
            if ($extKey === $extensionKey) {
                $hasEntry = true;
                break;
            }
        }

        if (!$hasEntry) {
            return [
                'file' => $file,
                'state' => ResultState::SUCCESS,
                'issues' => [],
                'action' => Action::UNCHANGED,
                'generated' => null,
            ];
        }

        $mutated = $this->withoutConstraintEntry($original, $type, $extensionKey);
        $validation = $this->validator->validate($mutated, $version);
        $blocking = $validation->state === ResultState::FAILURE
            || ($strict && $validation->state !== ResultState::SUCCESS);
        if ($blocking) {
            return [
                'file' => $file,
                'state' => $validation->state,
                'issues' => $validation->issues,
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
                'issues' => $validation->issues,
                'action' => Action::UNCHANGED,
                'generated' => $generated,
            ];
        }

        if ($dryRun) {
            return [
                'file' => $file,
                'state' => $validation->state,
                'issues' => $validation->issues,
                'action' => Action::DRY_RUN,
                'generated' => $generated,
            ];
        }

        file_put_contents($file, $generated, LOCK_EX);

        return [
            'file' => $file,
            'state' => $validation->state,
            'issues' => $validation->issues,
            'action' => Action::WRITTEN,
            'generated' => $generated,
        ];
    }

    private function withoutConstraintEntry(
        ExtEmConf $original,
        string $type,
        string $extensionKey,
    ): ExtEmConf {
        $existing = $this->groupCollection($original->constraints, $type);
        $entries = [];
        foreach ($existing->all() as $dependency) {
            if ($dependency->extensionKey !== $extensionKey) {
                $entries[] = $dependency;
            }
        }
        $newCollection = new DependencyCollection($entries);

        $newConstraints = new Constraints(
            depends: $type === 'depends' ? $newCollection : $original->constraints->depends,
            conflicts: $type === 'conflicts' ? $newCollection : $original->constraints->conflicts,
            suggests: $type === 'suggests' ? $newCollection : $original->constraints->suggests,
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
            constraints: $newConstraints,
            autoload: $original->autoload,
            autoloadDev: $original->autoloadDev,
            clearCacheOnLoad: $original->clearCacheOnLoad,
            uploadfolder: $original->uploadfolder,
            createDirs: $original->createDirs,
            unknownProperties: $original->unknownProperties,
        );
    }

    private function groupCollection(Constraints $constraints, string $type): DependencyCollection
    {
        return match ($type) {
            'depends' => $constraints->depends,
            'conflicts' => $constraints->conflicts,
            'suggests' => $constraints->suggests,
            default => throw new \LogicException('Unreachable: type validated up-front.'),
        };
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
        string $type,
        string $extensionKey,
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
            'command' => 'extemconf:constraints:unset',
            'type' => $type,
            'extensionKey' => $extensionKey,
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
