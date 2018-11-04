<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

interface RegistersRoutes
{
    /**
     * Get authentication route name.
     *
     * @return string
     */
    public function authRoute();

    /**
     * Get subscription auth group options.
     *
     * @return array
     */
    public function authGroup();

    /**
     * Get subscription controller and method.
     * Return null to disable.
     *
     * @return string|null
     */
    public function authController();

    /**
     * Get authentication route name.
     *
     * @return string
     */
    public function webhookRoute();

    /**
     * Get subscription auth group options.
     *
     * @return array
     */
    public function webhookGroup();

    /**
     * Get subscription controller and method.
     * Return null to disable.
     *
     * @return string|null
     */
    public function webhookController();
}
