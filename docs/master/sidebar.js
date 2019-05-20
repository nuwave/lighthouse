module.exports = [{
    title: 'Getting Started',
    children: [
        'getting-started/installation',
        'getting-started/configuration',
        'getting-started/tutorial',
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
        title: 'Queries',
        children: [
            'queries/pagination',
            'queries/resolvers',
            'queries/relationships',
            'queries/relay',
        ]
    },
    {
        title: 'Mutations',
        children: [
            ['mutations/getting-started', 'Getting Started'],
            'mutations/resolvers',
            'mutations/relationships',
            'mutations/relay',
            'mutations/file-uploads',
        ]
    },
    {
        title: 'Subscriptions',
        children: [
            ['subscriptions/getting-started', 'Getting Started'],
            'subscriptions/defining-fields',
            'subscriptions/trigger-subscriptions',
            'subscriptions/filtering-subscriptions',
        ]
    },
    {
        title: 'Custom Directives',
        children: [
            ['custom-directives/getting-started', 'Getting Started'],
            'custom-directives/node-directives',
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
            'security/resource-exhaustion',
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
        title: "Testing",
        children: [
            ['testing/getting-started', 'Getting Started'],
            'testing/playground',
            'testing/integration',
            'testing/unit',
            'testing/mocking',
        ],
    },
    {
        title: "Package Development",
        children: [
            ['package-development/getting-started', 'Getting Started'],
            'package-development/schema',
            'package-development/request',
            'package-development/events',
            'package-development/directives',
            'package-development/resolver',

        ],
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

    { // TODO: remove this section
        title: 'Guides',
        children: [
            'guides/schema-organisation',
            'guides/relay',
            'guides/error-handling',
            'guides/native-php-types'
        ]
    },
];
