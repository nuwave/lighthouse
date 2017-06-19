<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LaravelController extends Controller
{
    /**
     * Inject middleware into request.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        if ($query = $request->get('query')) {
            $middleware = app('graphql')->schema()
                ->parse($query)
                ->middleware();

            $this->middleware($middleware->toArray());
        }
    }

    /**
     * Execute graphql query.
     *
     * @param  Request $request
     * @return Response
     */
    public function query(Request $request)
    {
        $query = $request->get('query');
        $variables = $request->get('variables');

        if (is_string($variables)) {
            $variables = json_decode($variables, true);
        }

        return app('graphql')->execute($query, null, $variables);
    }
}
