<?php

GraphQL::addType('bar', 'foo');
GraphQL::addQuery('baz', 'bar');
GraphQL::addMutation('foo', 'baz');
