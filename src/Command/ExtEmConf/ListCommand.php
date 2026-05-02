<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf;

use Packwright\Console\Concern\Typo3VersionOption;
use Packwright\Console\DisplayPathFormatter;
use Packwright\ExtEmConf\Dto\Composer\SiblingComposerInfo;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Result\Issue;
use Packwright\ExtEmConf\Service\ExtEmConfArraySerializer;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
use Packwright\ExtEmConf\Service\ExtEmConfValidator;
use Packwright\ExtEmConf\Service\PathResolver;
use Packwright\ExtEmConf\Service\SiblingComposerReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'extemconf:list',
    description: 'List `ext_emconf.php` files matching given paths or patterns',
)]
final class ListCommand extends Command
{
    use Typo3VersionOption;

    public function __construct(
        private readonly PathResolver $pathResolver,
        private readonly ExtEmConfReader $reader,
        private readonly ExtEmConfValidator $validator,
        private readonly DisplayPathFormatter $displayPath,
        private readonly ExtEmConfArraySerializer $serializer,
        private readonly SiblingComposerReader $composerReader,
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
                description: 'Output a JSON document keyed by the folder containing each `ext_emconf.php` instead of a table.',
            )
            ->addOption(
                name: 'strict',
                mode: InputOption::VALUE_NONE,
                description: 'Exit with code 1 when any listed file ends in WARNING or FAILURE state.',
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

        $json = (bool)$input->getOption('json');
        $strict = (bool)$input->getOption('strict');

        if ($files === []) {
            $stderr->writeln('<comment>No files matched the given paths/patterns.</comment>');
            if ($json) {
                $output->writeln('{}');
            }

            return Command::SUCCESS;
        }

        $rows = [];
        $hasNonSuccess = false;
        foreach ($files as $file) {
            $row = $this->processFile($file, $version);
            $rows[] = $row;
            if ($row['state'] !== ResultState::SUCCESS) {
                $hasNonSuccess = true;
            }
        }

        $exitCode = ($strict && $hasNonSuccess) ? Command::FAILURE : Command::SUCCESS;

        if ($json) {
            $output->writeln($this->renderJson($rows));

            return $exitCode;
        }

        $this->renderTable($rows, $output);

        return $exitCode;
    }

    /**
     * @return array{file: string, title: string, version: string, state: ResultState, issues: list<Issue>, data: ?array<string, mixed>, composer: SiblingComposerInfo}
     */
    private function processFile(string $file, ?Typo3Version $version): array
    {
        $composer = $this->composerReader->read($file);

        $readResult = $this->reader->read($file);
        if ($readResult->extEmConf === null) {
            return [
                'file' => $file,
                'title' => '',
                'version' => '',
                'state' => ResultState::FAILURE,
                'issues' => $readResult->issues->all(),
                'data' => null,
                'composer' => $composer,
            ];
        }

        $validation = $this->validator->validate($readResult->extEmConf, $version);
        $issues = array_merge($readResult->issues->all(), $validation->issues->all());
        $state = $this->worstState($readResult->state, $validation->state);

        return [
            'file' => $file,
            'title' => $readResult->extEmConf->title,
            'version' => $readResult->extEmConf->version,
            'state' => $state,
            'issues' => array_values($issues),
            'data' => $this->serializer->serialize($readResult->extEmConf),
            'composer' => $composer,
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
     * @param list<array{file: string, title: string, version: string, state: ResultState, issues: list<Issue>, data: ?array<string, mixed>, composer: SiblingComposerInfo}> $rows
     */
    private function renderTable(array $rows, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders([
            'Path',
            'Extension Key',
            'Package Name',
            'Package Type',
            'Title',
            'Version',
            'State',
            'Issues',
        ]);

        foreach ($rows as $row) {
            $table->addRow([
                $this->displayPath->format($row['file']),
                $row['composer']->extensionKey,
                $row['composer']->packageName,
                $row['composer']->packageType,
                $row['title'],
                $row['version'],
                $this->stateLabel($row['state']),
                (string)count($row['issues']),
            ]);
        }

        $table->render();
    }

    /**
     * @param list<array{file: string, title: string, version: string, state: ResultState, issues: list<Issue>, data: ?array<string, mixed>, composer: SiblingComposerInfo}> $rows
     */
    private function renderJson(array $rows): string
    {
        $payload = [];
        foreach ($rows as $row) {
            $folder = dirname($row['file']);
            $composerPath = $folder . '/composer.json';

            $payload[$folder] = [
                'file' => is_file($row['file']) ? $row['file'] : '',
                'composerFile' => is_file($composerPath) ? $composerPath : '',
                'data' => $row['data'],
                'composer' => [
                    'extensionKey' => $row['composer']->extensionKey,
                    'packageName' => $row['composer']->packageName,
                    'packageType' => $row['composer']->packageType,
                    'packageDescription' => $row['composer']->packageDescription,
                ],
                'validation' => [
                    'state' => $row['state']->name,
                    'isValid' => $row['state'] !== ResultState::FAILURE,
                    'issues' => array_map(
                        static fn(Issue $i): array => [
                            'severity' => $i->severity->name,
                            'path' => $i->path,
                            'message' => $i->message,
                        ],
                        $row['issues'],
                    ),
                ],
            ];
        }

        return (string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
