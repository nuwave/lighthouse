module.exports = [
    {
        title: 'Getting Started',
        children: [
            'getting-started/installation',
            'getting-started/configuration',
            'getting-started/migrating-to-lighthouse'
        ]
    },
    {
        title: 'The Basics',
        children: [
            'the-basics/schema',
            'the-basics/types',
            'the-basics/fields',
            'the-basics/directives',
        ]
    },
    {
        title: 'Eloquent',
        children: [
            ['eloquent/getting-started', 'Getting Started'],
            'eloquent/relationships',
            'eloquent/polymorphic-relationships',
            'eloquent/nested-mutations',
        ]
    },
    {
        title: "Testing",
        children: [
            'testing/phpunit',
        ],
    },
    {
        title: 'Subscriptions',
        children: [
            ['subscriptions/getting-started', 'Getting Started'],
            'subscriptions/defining-fields',
            'subscriptions/trigger-subscriptions',
            'subscriptions/filtering-subscriptions',
            'subscriptions/client-implementations',
        ]
    },
    {
        title: 'Digging Deeper',
        children: [
            'digging-deeper/schema-organisation',
            'digging-deeper/client-directives',
            'digging-deeper/relay',
            'digging-deeper/error-handling',
            'digging-deeper/adding-types-programmatically',
            'digging-deeper/file-uploads',
            'digging-deeper/extending-lighthouse'
        ]
    },
    {
        title: 'Custom Directives',
        children: [
            ['custom-directives/getting-started', 'Getting Started'],
            'custom-directives/type-directives',
            'custom-directives/field-directives',
            'custom-directives/argument-directives',
        ]
    },
    {
        title: 'Security',
        children: [
            'security/authentication',
            'security/authorization',
            'security/validation',
            ['security/resource-exhaustion', 'Resource Exhaustion'],
        ]
    },
    {
        title: 'Performance',
        children: [
            'performance/schema-caching',
            ['performance/n-plus-one', 'The N+1 Query Problem'],
            'performance/deferred',
            'performance/tracing',
            'performance/server-configuration',
        ]
    },
    {
        title: 'API Reference',
        children: [
            'api-reference/directives',
            'api-reference/resolvers',
            'api-reference/scalars',
            'api-reference/events',
            'api-reference/commands',
        ]
    },
];
