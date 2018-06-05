<?php


namespace Nuwave\Lighthouse\Support\Contracts\GraphQl;



class Kind{
    const Document = 1;
    const Enum = 2;
    const Scalar = 3;
    const Interface = 4;
    const Object = 5;
    const InputObject = 6;
    const Directive = 7;
    const Extension = 8;
    const Union = 9;
}