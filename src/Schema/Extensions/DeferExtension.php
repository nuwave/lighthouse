<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Http\Responses\CanSendResponse;

class DeferExtension extends GraphQLExtension
{
    /** @var CanSendResponse */
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
     * @param CanSendResponse $stream
     */
    public function __construct(CanSendResponse $stream)
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
     */
    public function defer(\Closure $resolver, string $path)
    {
        if ($data = array_get($this->data, "data.{$path}")) {
            return $data;
        }

        if ($this->hasResolver($path)) {
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
        $hasResolver = $this->hasResolver($path);
        $resolver = $hasResolver ? $this->deferred[$path] : $originalResolver;

        if ($hasResolver) {
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
    public function hasResolver(string $path)
    {
        return isset($this->deferred[$path]);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function hasData(string $path)
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
            $this->stream->send($data, [], empty($this->deferred));

            while (count($this->deferred)) {
                // TODO: Properly parse variables array
                // TODO: Get debug setting
                $this->data = graphql()->executeQuery(
                    $this->request->request()->input('query', ''),
                    $this->request->context(),
                    $this->request->request()->input('variables', [])
                )->toArray(config('lighthouse.debug'));

                $this->stream->send(
                    $this->data,
                    $this->resolved,
                    empty($this->deferred)
                );

                $this->resolved = [];
            }
        }, 200, [
            'X-Accel-Buffering' => 'no',
            'Content-Type' => 'multipart/mixed; boundary="-"',
            // 'Connection' => 'keep-alive',
            // 'Transfer-Encoding' => 'chunked',
        ]);
    }
}
