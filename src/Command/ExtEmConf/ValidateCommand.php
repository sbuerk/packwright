<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf;

use Packwright\Console\Concern\Typo3VersionOption;
use Packwright\Console\DisplayPathFormatter;
use Packwright\ExtEmConf\Enum\IssueSeverity;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Result\Issue;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
use Packwright\ExtEmConf\Service\ExtEmConfValidator;
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
    name: 'extemconf:validate',
    description: 'Validate one or more `ext_emconf.php` files',
)]
final class ValidateCommand extends Command
{
    use Typo3VersionOption;

    public function __construct(
        private readonly PathResolver $pathResolver,
        private readonly ExtEmConfReader $reader,
        private readonly ExtEmConfValidator $validator,
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
                name: 'json',
                mode: InputOption::VALUE_NONE,
                description: 'Output a JSON document instead of a table.',
            )
            ->addOption(
                name: 'fail-on-warning',
                mode: InputOption::VALUE_NONE,
                description: 'Exit with code 1 when any file ends in WARNING state.',
            );

        $this->configureTypo3VersionOption('validate');
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

        $rows = [];
        $hasWarnings = false;
        $hasFailures = false;
        foreach ($files as $file) {
            $row = $this->processFile($file, $version);
            $rows[] = $row;
            if ($row['state'] === ResultState::FAILURE) {
                $hasFailures = true;
            } elseif ($row['state'] === ResultState::WARNING) {
                $hasWarnings = true;
            }
        }

        $exitCode = Command::SUCCESS;
        if ($hasFailures) {
            $exitCode = Command::FAILURE;
        } elseif ($input->getOption('fail-on-warning') && $hasWarnings) {
            $exitCode = Command::FAILURE;
        }

        if ($input->getOption('json')) {
            $output->writeln($this->renderJson($rows, $version));

            return $exitCode;
        }

        $this->renderTable($rows, $version, $output);

        return $exitCode;
    }

    /**
     * @return array{file: string, state: ResultState, issues: list<Issue>}
     */
    private function processFile(string $file, ?Typo3Version $version): array
    {
        $readResult = $this->reader->read($file);
        if ($readResult->extEmConf === null) {
            return [
                'file' => $file,
                'state' => ResultState::FAILURE,
                'issues' => $readResult->issues,
            ];
        }

        $validation = $this->validator->validate($readResult->extEmConf, $version);
        $issues = array_merge($readResult->issues, $validation->issues);
        $state = $this->worstState($readResult->state, $validation->state);

        return [
            'file' => $file,
            'state' => $state,
            'issues' => array_values($issues),
        ];
    }

    private function worstState(ResultState $a, ResultState $b): ResultState
    {
        $rank = [
            ResultState::SUCCESS->name => 0,
            ResultState::WARNING->name => 1,
            ResultState::FAILURE->name => 2,
        ];

        return $rank[$a->name] >= $rank[$b->name] ? $a : $b;
    }

    /**
     * @param list<array{file: string, state: ResultState, issues: list<Issue>}> $rows
     */
    private function renderTable(array $rows, ?Typo3Version $version, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['File', 'TYPO3', 'State', 'Errors', 'Warnings']);

        $versionLabel = $version?->label() ?? 'any';

        foreach ($rows as $row) {
            $errors = $this->countBySeverity($row['issues'], IssueSeverity::ERROR);
            $warnings = $this->countBySeverity($row['issues'], IssueSeverity::WARNING);

            $table->addRow([
                $this->displayPath->format($row['file']),
                $versionLabel,
                $this->stateLabel($row['state']),
                (string)$errors,
                (string)$warnings,
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
     * @param list<array{file: string, state: ResultState, issues: list<Issue>}> $rows
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
     * @param list<array{file: string, state: ResultState, issues: list<Issue>}> $rows
     */
    private function renderJson(array $rows, ?Typo3Version $version): string
    {
        $files = [];
        $summary = [
            'total' => count($rows),
            'success' => 0,
            'warning' => 0,
            'failure' => 0,
        ];

        foreach ($rows as $row) {
            $errors = $this->countBySeverity($row['issues'], IssueSeverity::ERROR);
            $warnings = $this->countBySeverity($row['issues'], IssueSeverity::WARNING);

            $files[] = [
                'path' => $row['file'],
                'state' => $row['state']->name,
                'errorCount' => $errors,
                'warningCount' => $warnings,
                'issues' => array_map(
                    static fn(Issue $i): array => [
                        'severity' => $i->severity->name,
                        'path' => $i->path,
                        'message' => $i->message,
                    ],
                    $row['issues'],
                ),
            ];

            $summary[strtolower($row['state']->name)]++;
        }

        $payload = [
            'command' => 'extemconf:validate',
            'typo3Version' => $version?->value,
            'files' => $files,
            'summary' => $summary,
        ];

        return (string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param list<Issue> $issues
     */
    private function countBySeverity(array $issues, IssueSeverity $severity): int
    {
        $count = 0;
        foreach ($issues as $issue) {
            if ($issue->severity === $severity) {
                $count++;
            }
        }

        return $count;
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
