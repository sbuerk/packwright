<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf;

use Packwright\Console\Concern\Typo3VersionOption;
use Packwright\Console\DisplayPathFormatter;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\State;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
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
    name: 'extemconf:get',
    description: 'Read simple scalar fields from one or more `ext_emconf.php` files',
)]
final class GetCommand extends Command
{
    use Typo3VersionOption;

    /**
     * Mapping from the snake_case CLI/`ext_emconf.php` key to the
     * camelCase DTO property name.
     */
    private const FIELD_MAP = [
        'title' => 'title',
        'description' => 'description',
        'version' => 'version',
        'category' => 'category',
        'state' => 'state',
        'author' => 'author',
        'author_email' => 'authorEmail',
        'author_company' => 'authorCompany',
    ];

    public function __construct(
        private readonly PathResolver $pathResolver,
        private readonly ExtEmConfReader $reader,
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
                name: 'field',
                shortcut: 'f',
                mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                description: 'Restrict output to one or more fields (repeatable). '
                    . 'Allowed: ' . implode(', ', array_keys(self::FIELD_MAP)) . '.',
            )
            ->addOption(
                name: 'json',
                mode: InputOption::VALUE_NONE,
                description: 'Output a JSON document instead of a table.',
            );

        $this->configureTypo3VersionOption('read against');
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

        /** @var list<string> $rawFields */
        $rawFields = $input->getOption('field');
        $fields = $this->resolveFieldSelection($rawFields);
        if ($fields === null) {
            $unknown = array_diff($rawFields, array_keys(self::FIELD_MAP));
            $stderr->writeln(sprintf(
                '<error>Unknown field(s): %s. Allowed: %s.</error>',
                implode(', ', $unknown),
                implode(', ', array_keys(self::FIELD_MAP)),
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
        $exitCode = Command::SUCCESS;
        foreach ($files as $file) {
            $row = $this->processFile($file);
            $rows[] = $row;
            if ($row['extEmConf'] === null) {
                $exitCode = Command::FAILURE;
            }
        }

        if ($input->getOption('json')) {
            $output->writeln($this->renderJson($rows, $fields));

            return $exitCode;
        }

        $this->renderTable($rows, $fields, $output);

        return $exitCode;
    }

    /**
     * @param list<string> $rawFields
     *
     * @return list<string>|null `null` on unknown field; otherwise the selection (default = all).
     */
    private function resolveFieldSelection(array $rawFields): ?array
    {
        if ($rawFields === []) {
            return array_keys(self::FIELD_MAP);
        }
        foreach ($rawFields as $field) {
            if (!isset(self::FIELD_MAP[$field])) {
                return null;
            }
        }

        return array_values(array_unique($rawFields));
    }

    /**
     * @return array{file: string, extEmConf: ?ExtEmConf, error: ?string}
     */
    private function processFile(string $file): array
    {
        $readResult = $this->reader->read($file);
        if ($readResult->extEmConf === null) {
            $issues = $readResult->issues->all();
            $message = $issues === []
                ? 'Unable to read file.'
                : $issues[0]->message;

            return ['file' => $file, 'extEmConf' => null, 'error' => $message];
        }

        return ['file' => $file, 'extEmConf' => $readResult->extEmConf, 'error' => null];
    }

    /**
     * @param list<array{file: string, extEmConf: ?ExtEmConf, error: ?string}> $rows
     * @param list<string>                                                     $fields
     */
    private function renderTable(array $rows, array $fields, OutputInterface $output): void
    {
        $first = true;
        foreach ($rows as $row) {
            if (!$first) {
                $output->writeln('');
            }
            $first = false;

            $output->writeln(sprintf('<comment>%s</comment>', $this->displayPath->format($row['file'])));

            if ($row['extEmConf'] === null) {
                $output->writeln(sprintf('  <error>%s</error>', $row['error'] ?? 'Unable to read file.'));

                continue;
            }

            $table = new Table($output);
            $table->setHeaders(['Field', 'Value']);
            foreach ($fields as $field) {
                $table->addRow([$field, $this->formatValue($this->extractValue($row['extEmConf'], $field))]);
            }
            $table->render();
        }
    }

    /**
     * @param list<array{file: string, extEmConf: ?ExtEmConf, error: ?string}> $rows
     * @param list<string>                                                     $fields
     */
    private function renderJson(array $rows, array $fields): string
    {
        $files = [];
        foreach ($rows as $row) {
            $entry = ['path' => $row['file']];
            if ($row['extEmConf'] === null) {
                $entry['error'] = $row['error'] ?? 'Unable to read file.';
                $files[] = $entry;

                continue;
            }
            $values = [];
            foreach ($fields as $field) {
                $values[$field] = $this->extractValue($row['extEmConf'], $field);
            }
            $entry['fields'] = $values;
            $files[] = $entry;
        }

        $payload = [
            'command' => 'extemconf:get',
            'files' => $files,
        ];

        return (string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function extractValue(ExtEmConf $extEmConf, string $field): ?string
    {
        $property = self::FIELD_MAP[$field];
        $raw = $extEmConf->{$property};

        if ($raw === null) {
            return null;
        }
        if ($raw instanceof Category || $raw instanceof State) {
            return $raw->value;
        }

        return (string)$raw;
    }

    private function formatValue(?string $value): string
    {
        if ($value === null) {
            return '<fg=gray>(null)</>';
        }
        if ($value === '') {
            return '<fg=gray>(empty)</>';
        }

        return $value;
    }
}
