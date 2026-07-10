<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Rector;

use Nuwave\Lighthouse\Schema\RootType;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\ObjectType;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Naming\VariableRenamer;
use Rector\Php\PhpVersionProvider;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersion;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class RootResolverSignatureRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var array<int, string|null> */
    protected array $paramNames = [];

    public function __construct(
        protected PhpVersionProvider $phpVersionProvider,
        protected VariableRenamer $variableRenamer,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Fix root resolver __invoke signatures to match Lighthouse calling convention', [
            new CodeSample(
                <<<'CODE_SAMPLE'
namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(array $args)
    {
        return [];
    }
}
CODE_SAMPLE,
                <<<'CODE_SAMPLE'
namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(null $root, array $args)
    {
        return [];
    }
}
CODE_SAMPLE,
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /** @param  array<string, mixed>  $configuration */
    public function configure(array $configuration): void
    {
        $paramNames = $configuration['paramNames'] ?? [];
        assert(is_array($paramNames), 'paramNames must be an array.');

        if (count($paramNames) > 4) {
            throw new InvalidConfigurationException('paramNames must have at most 4 elements.');
        }

        foreach ($paramNames as $name) {
            if ($name !== null && ! is_string($name)) {
                throw new InvalidConfigurationException('Each paramNames element must be a string or null.');
            }
        }

        $this->paramNames = $paramNames;
    }

    /** @param  Class_  $node */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isRootResolver($node)) {
            return null;
        }

        $invokeMethod = $node->getMethod('__invoke');
        if (! $invokeMethod instanceof ClassMethod) {
            return null;
        }

        if ($invokeMethod->params === []) {
            return null;
        }

        $changed = false;

        if ($this->isMissingRootParam($invokeMethod)) {
            $this->prependRootParam($invokeMethod);
            $changed = true;
        } elseif ($this->fixParamType($invokeMethod, 0, $this->rootTypeIdentifier())) {
            $changed = true;
        }

        if ($this->ensureArgsParam($invokeMethod)) {
            $changed = true;
        }

        if ($this->fixParamType($invokeMethod, 1, new Identifier('array'))) {
            $changed = true;
        }

        if (isset($invokeMethod->params[2]) && $this->fixObjectParam($invokeMethod, 2, \Nuwave\Lighthouse\Support\Contracts\GraphQLContext::class)) {
            $changed = true;
        }

        if (isset($invokeMethod->params[3]) && $this->fixObjectParam($invokeMethod, 3, \Nuwave\Lighthouse\Execution\ResolveInfo::class)) {
            $changed = true;
        }

        if ($this->normalizeNames($invokeMethod)) {
            $changed = true;
        }

        if (! $changed) {
            return null;
        }

        return $node;
    }

    protected function isRootResolver(Class_ $node): bool
    {
        $fqcn = $node->namespacedName?->toString();
        if ($fqcn === null) {
            return false;
        }

        foreach ($this->resolverNamespaces() as $namespace) {
            if ($this->isDirectChildOfNamespace($fqcn, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subscriptions are excluded — their resolver convention differs (they use subscriber classes, not __invoke).
     *
     * @return non-empty-list<string>
     */
    protected function resolverNamespaces(): array
    {
        $namespaces = [
            ...RootType::namespaces(RootType::QUERY),
            ...RootType::namespaces(RootType::MUTATION),
        ];

        if ($namespaces === []) {
            throw new \RuntimeException('Lighthouse resolver namespaces are empty. Ensure your Rector config includes bootstrapFiles with the Larastan bootstrap and Lighthouse config loaded.');
        }

        return $namespaces;
    }

    protected function isDirectChildOfNamespace(string $fqcn, string $namespace): bool
    {
        return str_starts_with($fqcn, $namespace . '\\')
            && ! str_contains(substr($fqcn, strlen($namespace) + 1), '\\');
    }

    protected function isMissingRootParam(ClassMethod $method): bool
    {
        if (count($method->params) !== 1) {
            return false;
        }

        $firstParam = $method->params[0];

        return $firstParam->type === null
            || ($firstParam->type instanceof Identifier && $firstParam->type->name === 'array');
    }

    protected function prependRootParam(ClassMethod $method): void
    {
        $rootParam = new Param(
            new Variable('root'),
            null,
            $this->rootTypeIdentifier(),
        );

        array_unshift($method->params, $rootParam);
    }

    protected function rootTypeIdentifier(): Identifier
    {
        if ($this->phpVersionProvider->isAtLeastPhpVersion(PhpVersion::PHP_82)) {
            return new Identifier('null');
        }

        return new Identifier('mixed');
    }

    protected function fixParamType(ClassMethod $method, int $index, Identifier $expectedType): bool
    {
        if (! isset($method->params[$index])) {
            return false;
        }

        $param = $method->params[$index];
        $currentType = $param->type;

        if ($currentType instanceof Identifier && $currentType->name === $expectedType->name) {
            return false;
        }

        $param->type = $expectedType;

        return true;
    }

    protected function ensureArgsParam(ClassMethod $method): bool
    {
        if (count($method->params) >= 2) {
            return false;
        }

        $method->params[] = new Param(new Variable('args'), null, new Identifier('array'));

        return true;
    }

    protected function fixObjectParam(ClassMethod $method, int $index, string $expectedClass): bool
    {
        $param = $method->params[$index];
        $currentType = $param->type;

        if ($currentType instanceof FullyQualified || $currentType instanceof Node\Name) {
            $objectType = new ObjectType($currentType->toString());
            $expectedType = new ObjectType($expectedClass);

            if ($expectedType->isSuperTypeOf($objectType)->yes()) {
                return false;
            }
        }

        $param->type = new FullyQualified($expectedClass);

        return true;
    }

    protected function normalizeNames(ClassMethod $method): bool
    {
        if ($this->paramNames === []) {
            return false;
        }

        $changed = false;

        foreach ($this->paramNames as $index => $name) {
            if ($name === null) {
                continue;
            }

            if (! isset($method->params[$index])) {
                continue;
            }

            $param = $method->params[$index];
            if (! $param->var instanceof Variable) {
                continue;
            }

            $oldName = $param->var->name;
            if (! is_string($oldName) || $oldName === $name) {
                continue;
            }

            $param->var = new Variable($name);
            $this->variableRenamer->renameVariableInFunctionLike($method, $oldName, $name);
            $changed = true;
        }

        return $changed;
    }
}
