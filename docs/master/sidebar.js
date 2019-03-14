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
        ]
    },
    {
        title: 'Types',
        children: [
            ['types/getting-started', 'Getting Started'],
            'types/scalars',
            'types/enums',
        ]
    },
    {
        title: 'Queries',
        children: [
            ['queries/getting-started', 'Getting Started'],
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
        title: 'Security',
        children: [
            ['security/getting-started', 'Getting Started'],
            'security/authentication',
            'security/authorization',
            'security/validation',
        ]
    },
    {
        title: 'Extensions',
        children: [
            ['extensions/getting-started', 'Getting Started'],
            'extensions/subscriptions',
            'extensions/deferred',
            'extensions/tracing',
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

        ],
    },
    {
        title: 'API Reference',
        children: [
            'api-reference/directives',
            'api-reference/resolvers',
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
            'guides/custom-directives',
            'guides/error-handling',
            'guides/plugin-development',
        ]
    },
];
