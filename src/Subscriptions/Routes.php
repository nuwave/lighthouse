<?php

namespace Nuwave\Lighthouse\Subscriptions;

use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Nuwave\Lighthouse\Schema\Extensions\SubscriptionExtension;
use Nuwave\Lighthouse\Subscriptions\Contracts\RegistersRoutes;

class Routes implements RegistersRoutes
{
    /** @var ExtensionRegistry */
    protected $extensions;

    /** @var array */
    protected $authRoute = [
        'route' => 'graphql/subscriptions/auth',
        'controller' => 'Nuwave\Lighthouse\Support\Http\Controllers\SubscriptionController@authorize',
        'group' => [
            'prefix' => '',
        ],
    ];

    /** @var array */
    protected $webookRoute = [
        'route' => 'graphql/subscriptions/webhook',
        'controller' => 'Nuwave\Lighthouse\Support\Http\Controllers\SubscriptionController@webhook',
        'group' => [
            'prefix' => '',
        ],
    ];

    /**
     * @param ExtensionRegistry $extensions
     */
    public function __construct(ExtensionRegistry $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * Get authentication route name.
     *
     * @return string
     */
    public function authRoute()
    {
        return $this->authRoute['route'];
    }

    /**
     * Get subscription auth group options.
     *
     * @return array
     */
    public function authGroup()
    {
        return $this->authRoute['group'];
    }

    /**
     * Get subscription controller and method.
     * Return null to disable.
     *
     * @return string|null
     */
    public function authController()
    {
        return $this->activated() ? $this->authRoute['controller'] : null;
    }

    /**
     * Get authentication route name.
     *
     * @return string
     */
    public function webhookRoute()
    {
        return $this->authRoute['route'];
    }

    /**
     * Get subscription auth group options.
     *
     * @return array
     */
    public function webhookGroup()
    {
        return $this->authRoute['group'];
    }

    /**
     * Get subscription controller and method.
     * Return null to disable.
     *
     * @return string|null
     */
    public function webhookController()
    {
        return $this->activated() ? $this->authRoute['controller'] : null;
    }

    /**
     * @return bool
     */
    protected function activated()
    {
        return $this->extensions->has(SubscriptionExtension::name());
    }
}
