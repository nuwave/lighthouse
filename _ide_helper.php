<?php

namespace Illuminate\Foundation\Testing {
    class TestResponse
    {
        /**
         * Asserts that the response contains an error from a given category.
         *
         * @param  string  $category  The name of the expected error category.
         * @return $this
         */
        public function assertGraphQLErrorCategory(string $category): self
        {
            return $this;
        }

        /**
         * Assert that the returned result contains exactly the given validation keys.
         *
         * @param  array  $keys  The validation keys the result should have.
         * @return $this
         */
        public function assertGraphQLValidationKeys(array $keys): self
        {
            return $this;
        }

        /**
         * Assert that a given validation error is present in the response.
         *
         * @param  string  $key  The validation key that should be present.
         * @param  string  $message  The expected validation message.
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
    }
}

namespace Illuminate\Testing {
    class TestResponse
    {
        /**
         * Assert the response contains an error with the given message.
         *
         * @param  string  $message  The expected error message.
         * @return $this
         */
        public function assertGraphQLErrorMessage(string $message): self
        {
            return $this;
        }

        /**
         * Assert the response contains an error from the given category.
         *
         * @param  string  $category  The name of the expected error category.
         * @return $this
         */
        public function assertGraphQLErrorCategory(string $category): self
        {
            return $this;
        }

        /**
         * Assert the returned result contains exactly the given validation keys.
         *
         * @param  array  $keys  The validation keys the result should have.
         * @return $this
         */
        public function assertGraphQLValidationKeys(array $keys): self
        {
            return $this;
        }

        /**
         * Assert a given validation error is present in the response.
         *
         * @param  string  $key  The validation key that should be present.
         * @param  string  $message  The expected validation message.
         * @return $this
         */
        public function assertGraphQLValidationError(string $key, string $message): self
        {
            return $this;
        }

        /**
         * Assert no validation errors are present in the response.
         *
         * @return $this
         */
        public function assertGraphQLValidationPasses(): self
        {
            return $this;
        }
    }
}

namespace GraphQL\Type\Definition {
    class ResolveInfo
    {
        /**
         * We monkey patch this onto here to pass it down the resolver chain.
         *
         * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
         */
        public $argumentSet;
    }
}
