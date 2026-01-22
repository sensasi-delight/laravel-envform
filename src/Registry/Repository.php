<?php

declare(strict_types=1);

namespace EnvForm\Registry;

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
class Repository extends NodeVisitorAbstract
{
    /** @var string[] Current configuration path stack */
    private array $stack = [];

    /** @var Collection<int, array{envKey: string, configKey: string, defaultValue: mixed, file: string}> */
    private Collection $foundItems;

    private string $currentFilename = '';

    /**
     * Scan config directory for env() calls and return raw findings.
     *
     * @return Collection<int, array{envKey: string, configKey: string, defaultValue: mixed, file: string}>
     */
    public function scan(): Collection
    {
        $configPath = App::configPath();

        return $this->parseConfigDirectory($configPath);
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
        if ($expr instanceof Node\Scalar\String_ || $expr instanceof Node\Scalar\LNumber || $expr instanceof Node\Scalar\DNumber) {
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

        if ($expr instanceof Node\Expr\ClassConstFetch && $expr->name instanceof Node\Identifier && $expr->name->toString() === 'class') {
            if ($expr->class instanceof Node\Name) {
                return $expr->class->toString();
            }
        }

        if ($expr instanceof Node\Expr\FuncCall && $expr->name instanceof Node\Name && $expr->name->toString() === 'env') {
            $args = $expr->getArgs();

            return isset($args[1]) ? $this->parseDefaultValue($args[1]->value) : null;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getDependencyMap(): array
    {
        $path = __DIR__.'/../../resources/dependencies.php';

        return file_exists($path) ? require $path : [];
    }

    public function getStaticValue(string $file, string $dotPath): mixed
    {
        $node = $this->findNodeAtConfigPath($file, $dotPath);

        if ($node instanceof Node\Expr) {
            return $this->parseDefaultValue($node);
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function getStaticKeys(string $file, string $dotPath): array
    {
        $node = $this->findNodeAtConfigPath($file, $dotPath);

        if (! $node instanceof Node\Expr\Array_) {
            return [];
        }

        $keys = [];
        foreach ($node->items as $item) {
            if ($item->key instanceof Node\Scalar\String_) {
                $keys[] = $item->key->value;
            }
        }

        return $keys;
    }

    private function findNodeAtConfigPath(string $file, string $dotPath): ?Node
    {
        $configPath = App::configPath("{$file}.php");

        if (! file_exists($configPath)) {
            return null;
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        try {
            $content = file_get_contents($configPath);
            if ($content === false) {
                return null;
            }
            $stmts = $parser->parse($content);
        } catch (\Throwable $e) {
            return null;
        }

        if ($stmts === null) {
            return null;
        }

        // Find return statement
        $returnStmt = null;
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_) {
                $returnStmt = $stmt;
                break;
            }
        }

        if (! $returnStmt || ! $returnStmt->expr instanceof Node\Expr) {
            return null;
        }

        $currentExpr = $returnStmt->expr;

        if (empty($dotPath)) {
            return $currentExpr;
        }

        $keys = explode('.', $dotPath);

        foreach ($keys as $key) {
            if (! $currentExpr instanceof Node\Expr\Array_) {
                return null;
            }

            $found = false;
            foreach ($currentExpr->items as $item) {
                if ($item->key instanceof Node\Scalar\String_ &&
                    $item->key->value === $key
                ) {
                    $currentExpr = $item->value;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return null;
            }
        }

        return $currentExpr;
    }
}
