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
            'queries/relay',
        ]
    },
    {
        title: 'Mutations',
        children: [
            ['mutations/getting-started', 'Getting Started'],
            'mutations/resolvers',
            'mutations/relay',
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
            'custom-directives/getting-started',
            'custom-directives/node-directives',
            'custom-directives/field-directives',
            'custom-directives/arg-directives',
        ]
    },
    {
        title: 'Security',
        children: [
            ['security/getting-started', 'Getting Started'],
            'security/authentication',
            'security/authorization',
            'security/validation',
        ]
    },
    {
        title: 'Performance',
        children: [
            ['performance/getting-started', 'Getting Started'],
            'performance/deferred',
            'performance/tracing',
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
            'package-development/directives',

        ],
    },
    {
        title: 'API Reference',
        children: [
            'api-reference/directives',
            'api-reference/resolvers',
            'api-reference/scalars',
            'api-reference/events',
        ]
    },

    { // TODO: remove this section
        title: 'Guides',
        children: [
            'guides/schema-organisation',
            'guides/relay',
            'guides/auth',
            'guides/validation',
            'guides/relationships',
            'guides/file-uploads',
            'guides/error-handling',
            'guides/plugin-development'
        ]
    },
];
