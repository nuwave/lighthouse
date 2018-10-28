<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\CanStreamResponse;

class DeferExtension extends GraphQLExtension
{
    /** @var CanStreamResponse */
    protected $stream;

    /** @var ExtensionRequest */
    protected $request;

    /** @var array */
    protected $data = [];

    /** @var array */
    protected $deferred = [];

    /** @var array */
    protected $resolved = [];

    /** @var bool */
    protected $streaming = false;

    /**
     * @param CanStreamResponse $stream
     */
    public function __construct(CanStreamResponse $stream)
    {
        $this->stream = $stream;
    }

    /**
     * The extension name controls under which key
     * the extensions shows up in the result.
     *
     * @return string
     */
    public static function name()
    {
        return 'defer';
    }

    /**
     * Handle request start.
     *
     * @param ExtensionRequest $request
     */
    public function requestDidStart(ExtensionRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Set the tracing directive on all fields of the query to enable tracing them.
     *
     * @param DocumentAST $documentAST
     *
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     *
     * @return DocumentAST
     */
    public function manipulateSchema(DocumentAST $documentAST): DocumentAST
    {
        $documentAST = ASTHelper::attachDirectiveToObjectTypeFields(
            $documentAST,
            PartialParser::directive('@deferrable')
        );

        $documentAST->setDefinition(
            PartialParser::directiveDefinition('directive @defer on FIELD')
        );

        return $documentAST;
    }

    /**
     * Format extension output.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [];
    }

    /**
     * @return bool
     */
    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Register deferred field.
     *
     * @param \Closure $resolver
     * @param string   $path
     *
     * @return mixed
     */
    public function defer(\Closure $resolver, string $path)
    {
        if ($data = array_get($this->data, "data.{$path}")) {
            return $data;
        }

        if ($this->isDeferred($path)) {
            return $this->resolve($resolver, $path);
        }

        $this->deferred[$path] = $resolver;

        return null;
    }

    /**
     * @param \Closure $originalResolver
     * @param string   $path
     *
     * @return mixed
     */
    public function findOrResolve(\Closure $originalResolver, string $path)
    {
        if (! $this->hasData($path)) {
            if (isset($this->deferred[$path])) {
                unset($this->deferred[$path]);
            }

            return $this->resolve($originalResolver, $path);
        }

        return $this->data[$path];
    }

    /**
     * Resolve field with data or resolver.
     *
     * @param \Closure $originalResolver
     * @param string   $path
     *
     * @return mixed
     */
    public function resolve(\Closure $originalResolver, string $path)
    {
        $isDeferred = $this->isDeferred($path);
        $resolver = $isDeferred ? $this->deferred[$path] : $originalResolver;

        if ($isDeferred) {
            $this->resolved[] = $path;

            unset($this->deferred[$path]);
        }

        return $resolver();
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function isDeferred(string $path): bool
    {
        return isset($this->deferred[$path]);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function hasData(string $path): bool
    {
        return isset($this->data[$path]);
    }

    /**
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    public function response(array $data)
    {
        if (empty($this->deferred)) {
            return response($data);
        }

        return response()->stream(function () use ($data) {
            $this->data = $data;
            $this->streaming = true;
            $this->stream->stream($data, [], empty($this->deferred));

            // TODO: Allow max_execution time and nested_levels to be set in config
            // to break out of loop early.
            while (count($this->deferred)) {
                // TODO: Properly parse variables array
                // TODO: Get debug setting
                $this->data = graphql()->executeQuery(
                    $this->request->request()->input('query', ''),
                    $this->request->context(),
                    $this->request->request()->input('variables', [])
                )->toArray(config('lighthouse.debug'));

                $this->stream->stream(
                    $this->data,
                    $this->resolved,
                    empty($this->deferred)
                );

                $this->resolved = [];
            }
        }, 200, [
            // TODO: Allow headers to be set in config
            'X-Accel-Buffering' => 'no',
            'Content-Type' => 'multipart/mixed; boundary="-"',
        ]);
    }
}
