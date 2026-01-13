<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class ConfigParser
{
    /**
     * @return Collection<string, array{key: string, config_path: string, file: string}>
     */
    public function parse(string $configPath): Collection
    {
        $files = Finder::create()
            ->files()
            ->in($configPath)
            ->name('*.php');

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $foundItems = collect();

        foreach ($files as $file) {
            $this->parseFile(
                $file,
                $parser,
                $foundItems
            );
        }

        return $foundItems;
    }

    /**
     * @param  \PhpParser\Parser  $parser
     * @param  \Illuminate\Support\Collection<string, array{key: string, config_path: string, file: string}>  $foundItems
     */
    private function parseFile(
        SplFileInfo $file,
        $parser,
        Collection $foundItems
    ): void {
        try {
            $stmts = $parser->parse($file->getContents());
            if ($stmts === null) {
                return;
            }
        } catch (\Throwable $e) {
            // Ignore parse errors for now
            return;
        }

        $traverser = new NodeTraverser;
        $visitor = new class($file->getFilenameWithoutExtension(), $foundItems) extends NodeVisitorAbstract
        {
            /** @var string[] */
            private array $stack = [];

            public function __construct(
                private readonly string $filename,

                /** @var Collection<string, array{key: string, config_path: string, file: string}> */
                private readonly Collection $foundItems
            ) {}

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Expr\ArrayItem) {
                    if ($node->key instanceof Node\Scalar\String_) {
                        $this->stack[] = $node->key->value;
                    } elseif ($node->key === null) {
                        // Indexed array, push valid identifier or index
                        $this->stack[] = '*';
                    }
                }

                if ($node instanceof Node\Expr\FuncCall) {
                    if ($node->name instanceof Node\Name && $node->name->toString() === 'env') {
                        $args = $node->getArgs();
                        if (isset($args[0]) && $args[0]->value instanceof Node\Scalar\String_) {
                            $envKey = $args[0]->value->value;
                            $configPath = $this->filename.'.'.implode('.', $this->stack);

                            // Env key logic
                            $this->foundItems->push([
                                'key' => $envKey,
                                'config_path' => $configPath,
                                'file' => $this->filename,
                            ]);
                        }
                    }
                }

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Expr\ArrayItem) {
                    if (($node->key instanceof Node\Scalar\String_) || $node->key === null) {
                        array_pop($this->stack);
                    }
                }

                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);
    }
}
