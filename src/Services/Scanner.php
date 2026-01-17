<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\Contracts\ScannerService;
use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Static analysis engine that combines AST traversal and Laravel's config structure.
 * Scans PHP files in the config directory to discover env() calls and their dot-notation paths.
 */
final class Scanner extends NodeVisitorAbstract implements ScannerService
{
    /** @var string[] Current configuration path stack */
    private array $stack = [];

    /** @var Collection<int, array{envKey: string, configKey: string, defaultValue: mixed, file: string}> */
    private Collection $foundItems;

    private string $currentFilename = '';

    /**
     * Scan config directory for env() calls and return a consolidated collection of EnvVars.
     *
     * @return Collection<int, EnvVar>
     *
     * @throws \Exception
     */
    public function scan(): Collection
    {
        $configPath = App::configPath();

        info("🔍 Analyzing project configuration in: {$configPath}...");

        $astRaw = $this->parseConfigDirectory($configPath);

        // Group by envKey to handle multiple config paths per env var
        return $astRaw->groupBy('envKey')
            ->map(function (Collection $occurrences, string $envKey) {
                $configKeys = $occurrences->pluck('configKey');
                $firstOccurrence = $occurrences->first();

                if ($firstOccurrence === null) {
                    throw new \Exception("Could not find any occurrences for {$envKey}", 1);
                }

                // Calculate dependencies based on RuleEngine
                $dependencies = [];
                foreach (RuleEngine::RULES as $triggerKey => $conditions) {
                    foreach ($conditions as $triggerValue => $patterns) {
                        foreach ($configKeys as $ck) {
                            foreach ($patterns as $pattern) {
                                if (fnmatch($pattern, $ck)) {
                                    $dependencies[$triggerKey][$triggerValue] = $patterns;
                                    break;
                                }
                            }
                        }
                    }
                }

                $isTrigger = $configKeys->contains(fn (string $configKey) => \array_key_exists($configKey, RuleEngine::RULES));

                return new EnvVar(
                    $configKeys,
                    $firstOccurrence['defaultValue'],
                    $dependencies,
                    $firstOccurrence['file'],
                    $firstOccurrence['file'], // Group by file
                    $isTrigger,
                    $envKey,
                );
            })->sortBy('key')->values();
    }

    /**
     * @return Collection<int, array{envKey: string, configKey: string, defaultValue: mixed, file: string}>
     */
    private function parseConfigDirectory(string $configPath): Collection
    {
        $files = Finder::create()
            ->files()
            ->in($configPath)
            ->name('*.php');

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->foundItems = new Collection;

        foreach ($files as $file) {
            $this->parseFile($file, $parser);
        }

        return $this->foundItems;
    }

    private function parseFile(SplFileInfo $file, Parser $parser): void
    {
        try {
            $stmts = $parser->parse($file->getContents());
            if ($stmts === null) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $this->currentFilename = $file->getFilenameWithoutExtension();
        $this->stack = [];

        $traverser = new NodeTraverser;
        $traverser->addVisitor($this);
        $traverser->traverse($stmts);
    }

    /**
     * @internal NodeVisitor API
     */
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Expr\ArrayItem) {
            if ($node->key instanceof Node\Scalar\String_) {
                $this->stack[] = $node->key->value;
            } elseif ($node->key === null) {
                $this->stack[] = '*';
            }
        }

        if ($node instanceof Node\Expr\FuncCall) {
            $this->handleFuncCall($node);
        }

        return null;
    }

    /**
     * @internal NodeVisitor API
     */
    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Node\Expr\ArrayItem) {
            if (($node->key instanceof Node\Scalar\String_) || $node->key === null) {
                array_pop($this->stack);
            }
        }

        return null;
    }

    private function handleFuncCall(Node\Expr\FuncCall $funcCall): void
    {
        if (! $funcCall->name instanceof Node\Name || $funcCall->name->toString() !== 'env') {
            return;
        }

        $args = $funcCall->getArgs();
        if (! isset($args[0]) || ! $args[0]->value instanceof Node\Scalar\String_) {
            return;
        }

        $envKey = $args[0]->value->value;
        $configKey = $this->currentFilename.'.'.implode('.', $this->stack);

        $defaultValue = null;
        if (isset($args[1])) {
            $defaultValue = $this->parseDefaultValue($args[1]->value);
        }

        $this->foundItems->push([
            'envKey' => $envKey,
            'configKey' => $configKey,
            'defaultValue' => $defaultValue,
            'file' => $this->currentFilename.'.php',
        ]);
    }

    private function parseDefaultValue(Node\Expr $expr): mixed
    {
        if ($expr instanceof Node\Scalar\String_ || $expr instanceof Node\Scalar\LNumber) {
            return $expr->value;
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            $constName = strtolower($expr->name->toString());

            return match ($constName) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => null,
            };
        }

        return null;
    }
}
