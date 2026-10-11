<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Rector;

use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
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
                badCode: <<<'CODE_SAMPLE'
namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(array $args)
    {
        return [];
    }
}
CODE_SAMPLE,
                goodCode: <<<'CODE_SAMPLE'
namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(mixed $root, array $args)
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
        if (! is_array($paramNames)) {
            throw new InvalidConfigurationException('paramNames must be an array.');
        }

        if (count($paramNames) > 4) {
            throw new InvalidConfigurationException('paramNames must have at most 4 elements.');
        }

        foreach ($paramNames as $name) {
            if ($name !== null && ! is_string($name)) {
                throw new InvalidConfigurationException('Each paramNames element must be a string or null.');
            }
        }

        $this->paramNames = array_values($paramNames);
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

        if ($this->isUselessSingleRootParam($invokeMethod)) {
            $invokeMethod->params = [];

            return $node;
        }

        $changed = false;

        if ($this->isMissingRootParam($invokeMethod)) {
            $this->prependRootParam($invokeMethod);
            $changed = true;
        } elseif ($this->fixParamType($invokeMethod, 0, $this->rootTypeIdentifier())) {
            $changed = true;
        }

        if ($this->fixParamType($invokeMethod, 1, new Identifier('array'))) {
            $changed = true;
        }

        if (isset($invokeMethod->params[2])
            && $this->fixObjectParam($invokeMethod, 2, GraphQLContext::class)
        ) {
            $changed = true;
        }

        if (isset($invokeMethod->params[3])
            && $this->fixObjectParam($invokeMethod, 3, ResolveInfo::class)
        ) {
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

    /** @return non-empty-list<string> */
    protected function resolverNamespaces(): array
    {
        return [
            ...RootType::namespaces(RootType::QUERY),
            ...RootType::namespaces(RootType::MUTATION),
        ];
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

        $type = $method->params[0]->type;

        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        return $type instanceof Identifier && $type->name === 'array';
    }

    protected function isUselessSingleRootParam(ClassMethod $method): bool
    {
        if (count($method->params) !== 1) {
            return false;
        }

        $type = $method->params[0]->type;

        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if (! $type instanceof Identifier) {
            return true;
        }

        return $type->name !== 'array';
    }

    protected function prependRootParam(ClassMethod $method): void
    {
        $rootParam = new Param(
            var: new Variable('root'),
            type: $this->rootTypeIdentifier(),
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

        if ($currentType instanceof Identifier
            && $currentType->name === $expectedType->name
        ) {
            return false;
        }

        $param->type = $expectedType;

        return true;
    }

    protected function fixObjectParam(ClassMethod $method, int $index, string $expectedClass): bool
    {
        $param = $method->params[$index];
        $currentType = $param->type;

        if ($currentType instanceof FullyQualified
            || $currentType instanceof Node\Name
        ) {
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
            $variable = $param->var;
            if (! $variable instanceof Variable) {
                continue;
            }

            $oldName = $variable->name;
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
