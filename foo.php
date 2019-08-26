<?php

class A extends \ArrayObject {}

function foo(array $bar) {}

$a = new A;
$a['foo'] = 3;
array_walk($a, function(){echo 1;});
