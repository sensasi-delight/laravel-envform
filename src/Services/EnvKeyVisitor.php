<?php

declare(strict_types=1);

namespace EnvForm\Services;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * PHP-Parser Node Visitor to extract env() calls from config arrays.
 * Traverses the AST to build the configuration path (dot notation) for each discovered key.
 */
final class EnvKeyVisitor extends NodeVisitorAbstract
{
    /** @var string[] */
    private array $stack = [];

    /** @var Collection<int, EnvVar> */
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
     * @return Collection<int, EnvVar>
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
        $configKey = $this->filename.'.'.implode('.', $this->stack);

        $defaultValue = null;
        if (isset($args[1])) {
            $defaultValue = $this->parseDefaultValue($args[1]->value);
        }

        $this->foundItems->push(new EnvVar(
            $configKey,
            [$configKey],
            Config::get($configKey),
            $defaultValue,
            [],
            '',
            $this->filename,
            'General',
            false,
            $envKey
        ));
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
