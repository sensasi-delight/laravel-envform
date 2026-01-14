<?php

declare(strict_types=1);

namespace EnvForm\Visitors;

use EnvForm\DTO\EnvKeyDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class EnvKeyVisitor extends NodeVisitorAbstract
{
    /** @var string[] */
    private array $stack = [];

    /** @var Collection<int, EnvKeyDefinition> */
    private Collection $foundItems;

    public function __construct(
        private readonly string $filename,
    ) {
        $this->foundItems = new Collection;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Expr\ArrayItem) {
            $this->handleArrayItemEnter($node);
        }

        if ($node instanceof Node\Expr\FuncCall) {
            $this->handleFuncCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Node\Expr\ArrayItem) {
            $this->handleArrayItemLeave($node);
        }

        return null;
    }

    /**
     * @return Collection<int, EnvKeyDefinition>
     */
    public function getFoundItems(): Collection
    {
        return $this->foundItems;
    }

    private function handleArrayItemEnter(Node\Expr\ArrayItem $node): void
    {
        if ($node->key instanceof Node\Scalar\String_) {
            $this->stack[] = $node->key->value;
        } elseif ($node->key === null) {
            // Indexed array, push valid identifier or index placeholder
            $this->stack[] = '*';
        }
    }

    private function handleArrayItemLeave(Node\Expr\ArrayItem $node): void
    {
        if (($node->key instanceof Node\Scalar\String_) || $node->key === null) {
            array_pop($this->stack);
        }
    }

    private function handleFuncCall(Node\Expr\FuncCall $node): void
    {
        if (! $node->name instanceof Node\Name || $node->name->toString() !== 'env') {
            return;
        }

        $args = $node->getArgs();
        if (! isset($args[0]) || ! $args[0]->value instanceof Node\Scalar\String_) {
            return;
        }

        $envKey = $args[0]->value->value;
        $configPath = $this->filename.'.'.implode('.', $this->stack);

        // Handle default value if present (2nd argument)
        $defaultValue = null;
        if (isset($args[1]) && $args[1]->value instanceof Node\Scalar\String_) {
            $defaultValue = $args[1]->value->value;
        } elseif (isset($args[1]) && $args[1]->value instanceof Node\Scalar\LNumber) {
            $defaultValue = $args[1]->value->value;
        } elseif (isset($args[1]) && property_exists($args[1]->value, 'name') && $args[1]->value->name instanceof Node\Name) {
            // Handle boolean/null constants (true, false, null)
            $constName = strtolower($args[1]->value->name->toString());
            if ($constName === 'true') {
                $defaultValue = true;
            }
            if ($constName === 'false') {
                $defaultValue = false;
            }
            if ($constName === 'null') {
                $defaultValue = null;
            }
        }

        $this->foundItems->push(new EnvKeyDefinition(
            key: $envKey,
            default: $defaultValue,
            file: $this->filename,
            description: '', // Description extraction not yet implemented
            group: 'General', // Default group
            configPath: $configPath,
            configPaths: [$configPath],
            currentValue: Config::get($configPath),
        ));
    }
}
