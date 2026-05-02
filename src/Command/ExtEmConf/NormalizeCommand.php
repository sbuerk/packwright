<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf;

use Packwright\Console\Concern\Typo3VersionOption;
use Packwright\Console\DisplayPathFormatter;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Result\Issue;
use Packwright\ExtEmConf\Service\ExtEmConfNormalizer;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
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
    name: 'extemconf:normalize',
    description: 'Normalize one or more `ext_emconf.php` files',
)]
final class NormalizeCommand extends Command
{
    use Typo3VersionOption;

    public function __construct(
        private readonly PathResolver $pathResolver,
        private readonly ExtEmConfReader $reader,
        private readonly ExtEmConfNormalizer $normalizer,
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

        $this->configureTypo3VersionOption('normalize for');
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

        $dryRun = (bool)$input->getOption('dry-run');

        $rows = [];
        $exitCode = Command::SUCCESS;
        foreach ($files as $file) {
            $row = $this->processFile($file, $version, $dryRun);
            $rows[] = $row;
            if ($row['state'] === ResultState::FAILURE) {
                $exitCode = Command::FAILURE;
            }
        }

        if ($input->getOption('json')) {
            $output->writeln($this->renderJson($rows, $version, $dryRun));

            return $exitCode;
        }

        $this->renderTable($rows, $version, $output);

        return $exitCode;
    }

    /**
     * @return array{
     *     file: string,
     *     state: ResultState,
     *     hasChanges: bool,
     *     droppedItems: list<Issue>,
     *     action: Action,
     *     generated: ?string,
     * }
     */
    private function processFile(string $file, ?Typo3Version $version, bool $dryRun): array
    {
        $readResult = $this->reader->read($file);
        if ($readResult->extEmConf === null) {
            return [
                'file' => $file,
                'state' => ResultState::FAILURE,
                'hasChanges' => false,
                'droppedItems' => $readResult->issues,
                'action' => Action::SKIPPED,
                'generated' => null,
            ];
        }

        $normalization = $this->normalizer->normalize($readResult->extEmConf, $version);

        if ($normalization->state === ResultState::FAILURE) {
            return [
                'file' => $file,
                'state' => ResultState::FAILURE,
                'hasChanges' => $normalization->hasChanges,
                'droppedItems' => $normalization->droppedItems,
                'action' => Action::SKIPPED,
                'generated' => null,
            ];
        }

        $generated = $this->writer->generate($normalization->extEmConf, $version);

        if (!$normalization->hasChanges) {
            return [
                'file' => $file,
                'state' => $normalization->state,
                'hasChanges' => false,
                'droppedItems' => $normalization->droppedItems,
                'action' => Action::UNCHANGED,
                'generated' => $generated,
            ];
        }

        if ($dryRun) {
            return [
                'file' => $file,
                'state' => $normalization->state,
                'hasChanges' => true,
                'droppedItems' => $normalization->droppedItems,
                'action' => Action::DRY_RUN,
                'generated' => $generated,
            ];
        }

        file_put_contents($file, $generated, LOCK_EX);

        return [
            'file' => $file,
            'state' => $normalization->state,
            'hasChanges' => true,
            'droppedItems' => $normalization->droppedItems,
            'action' => Action::WRITTEN,
            'generated' => $generated,
        ];
    }

    /**
     * @param list<array{file: string, state: ResultState, hasChanges: bool, droppedItems: list<Issue>, action: Action, generated: ?string}> $rows
     */
    private function renderTable(array $rows, ?Typo3Version $version, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['File', 'TYPO3', 'State', 'Changes', 'Dropped', 'Action']);

        $versionLabel = $version?->label() ?? 'any';

        foreach ($rows as $row) {
            $table->addRow([
                $this->displayPath->format($row['file']),
                $versionLabel,
                $this->stateLabel($row['state']),
                $row['hasChanges'] ? 'yes' : 'no',
                (string)count($row['droppedItems']),
                $row['action']->label(),
            ]);
        }

        $table->render();

        if ($output->isVerbose()) {
            $this->renderDroppedDetails($rows, $output);

            return;
        }

        foreach ($rows as $row) {
            if ($row['droppedItems'] !== []) {
                $output->writeln('');
                $output->writeln('<info>Tip: re-run with -v to see dropped-item details.</info>');

                return;
            }
        }
    }

    /**
     * @param list<array{file: string, state: ResultState, hasChanges: bool, droppedItems: list<Issue>, action: Action, generated: ?string}> $rows
     */
    private function renderDroppedDetails(array $rows, OutputInterface $output): void
    {
        foreach ($rows as $row) {
            if ($row['droppedItems'] === []) {
                continue;
            }
            $output->writeln('');
            $output->writeln(sprintf('<comment>%s</comment>', $this->displayPath->format($row['file'])));
            foreach ($row['droppedItems'] as $issue) {
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
     * @param list<array{file: string, state: ResultState, hasChanges: bool, droppedItems: list<Issue>, action: Action, generated: ?string}> $rows
     */
    private function renderJson(array $rows, ?Typo3Version $version, bool $dryRun): string
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
                'hasChanges' => $row['hasChanges'],
                'droppedItems' => array_map(
                    static fn(Issue $i): array => [
                        'severity' => $i->severity->name,
                        'path' => $i->path,
                        'message' => $i->message,
                    ],
                    $row['droppedItems'],
                ),
                'action' => $row['action']->label(),
                'generated' => $row['generated'],
            ];

            $summary[$this->summaryKey($row['action'])]++;
            if ($row['state'] === ResultState::FAILURE) {
                $summary['failure']++;
            }
        }

        $payload = [
            'command' => 'extemconf:normalize',
            'typo3Version' => $version?->value,
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
