<?php

namespace Illuminate\Foundation\Testing {
    class TestResponse
    {
        /**
         * Asserts that the response contains an error from a given category.
         *
         * @param  string  $category
         * @return $this
         */
        public function assertErrorCategory(string $category): self
        {
            return $this;
        }

        /**
         * Just here for compatibility with Laravel 5.5, delete once we drop support.
         *
         * @param  string|null  $key
         * @return mixed
         */
        public function jsonGet(string $key = null) {
            return;
        }
    }
}

namespace Illuminate\Testing {
    class TestResponse
    {
        /**
         * Asserts that the response contains an error from a given category.
         *
         * @param  string  $category
         * @return $this
         */
        public function assertErrorCategory(string $category): self
        {
            return $this;
        }

        /**
         * Just here for compatibility with Laravel 5.5, delete once we drop support.
         *
         * @param  string|null  $key
         * @return mixed
         */
        public function jsonGet(string $key = null) {
            return;
        }
    }
}

namespace GraphQL\Type\Definition {
    class ResolveInfo
    {
        /**
         * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
         */
        public $argumentSet;
    }
}
