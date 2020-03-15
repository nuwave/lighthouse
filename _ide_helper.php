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
        public function assertGraphQLErrorCategory(string $category): self
        {
            return $this;
        }

        /**
         * Assert that the returned result contains an exactly defined array of validation keys.
         *
         * @param  array  $keys
         * @return $this
         */
        public function assertGraphQLValidationKeys(array $keys): self
        {
            return $this;
        }

        /**
         * Assert that a given validation error is present in the response.
         *
         * @param  string  $key
         * @param  string  $message
         * @return $this
         */
        public function assertGraphQLValidationError(string $key, string $message): self
        {
            return $this;
        }

        /**
         * Assert that no validation errors are present in the response.
         *
         * @return $this
         */
        public function assertGraphQLValidationPasses(): self
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
        public function assertGraphQLErrorCategory(string $category): self
        {
            return $this;
        }

        /**
         * Assert that the returned result contains an exactly defined array of validation keys.
         *
         * @param  array  $keys
         * @return $this
         */
        public function assertGraphQLValidationKeys(array $keys): self
        {
            return $this;
        }

        /**
         * Assert that a given validation error is present in the response.
         *
         * @param  string  $key
         * @param  string  $message
         * @return $this
         */
        public function assertGraphQLValidationError(string $key, string $message): self
        {
            return $this;
        }

        /**
         * Assert that no validation errors are present in the response.
         *
         * @return $this
         */
        public function assertGraphQLValidationPasses(): self
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
