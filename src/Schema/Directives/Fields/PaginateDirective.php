<?php


namespace Nuwave\Lighthouse\Schema\Directives\Fields;


use Closure;
use Nuwave\Lighthouse\Schema\ManipulatorInfo;
use Nuwave\Lighthouse\Schema\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\Directives\FieldDirective;
use Nuwave\Lighthouse\Support\Contracts\Directives\ManipulatorDirective;
use Nuwave\Lighthouse\Types\Argument;
use Nuwave\Lighthouse\Types\NonNullType;
use Nuwave\Lighthouse\Types\Scalar\IntType;
use Nuwave\Lighthouse\Types\Scalar\StringType;

class PaginateDirective implements FieldDirective, ManipulatorDirective
{

    public function name()
    {
        return 'paginate';
    }

    public function handleField(ResolveInfo $resolveInfo, Closure $next)
    {

    }

    public function handleManipulator(ManipulatorInfo $info, Closure $next)
    {
       //dd($info->field()->type()->getUnderlyingName());

        $info->field()->addArgument(new Argument(
            "count",
            null,
            NonNullType::ofType(IntType::instance())
        ));
        //dd($info->schema()->type('Query')->resolvedField('users')->arguments());
        return $next($info);
    }
}
