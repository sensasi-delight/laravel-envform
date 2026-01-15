<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvKeyDefinition;
use EnvForm\Visitors\EnvKeyVisitor;
use Illuminate\Support\Collection;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ConfigParser
{
    /**
     * @return Collection<int, EnvKeyDefinition>
     */
    public function parse(string $configPath): Collection
    {
        $files = Finder::create()
            ->files()
            ->in($configPath)
            ->name('*.php');

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        /** @var Collection<int, EnvKeyDefinition> $foundItems */
        $foundItems = new Collection;

        foreach ($files as $file) {
            $fileItems = $this->parseFile($file, $parser);
            $foundItems = $foundItems->merge($fileItems);
        }

        return $foundItems;
    }

    /**
     * @param  \PhpParser\Parser  $parser
     * @return Collection<int, EnvKeyDefinition>
     */
    private function parseFile(SplFileInfo $file, $parser): Collection
    {
        try {
            $stmts = $parser->parse($file->getContents());
            if ($stmts === null) {
                return new Collection;
            }
        } catch (\Throwable $e) {
            // Log error or ignore? For now, ignore as per original behavior.
            return new Collection;
        }

        $traverser = new NodeTraverser;
        $visitor = new EnvKeyVisitor($file->getFilenameWithoutExtension());

        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->getFoundItems();
    }
}
