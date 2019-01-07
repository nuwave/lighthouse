<?php

namespace Illuminate\Foundation\Testing {
    /**
     * Class TestResponse
     *
     * @package Illuminate\Foundation\Testing
     */
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
    }
}
