module.exports = {
  title: 'Lighthouse',
  description: 'A GraphQL server for Laravel',
  editLinks: true,
  lastUpdated: 'Last Updated',  //  Display "Last Updated" at the bottom of each page
  repo: 'nuwave/lighthouse',    //  Display "Edit this page" on Github at the bottom of each page
  docsDir: 'docs/website',
  themeConfig: {
    nav: [
      { text: 'Docs', link: '/docs/getting-started/installation' },
      { text: 'Resources', link: '/pages/resources' },
      { text: 'Users', link: '/pages/users' },
      { text: 'Github', link: 'https://github.com/nuwave/lighthouse' },
    ],
    sidebar: [
      {
        title: 'Getting Started',
        children: [
          '/docs/getting-started/installation',
          '/docs/getting-started/configuration',
          '/docs/getting-started/tutorial',
        ]
      },
      {
        title: 'The Basics',
        children: [
          '/docs/the-basics/schema',
          '/docs/the-basics/types',
          '/docs/the-basics/fields',
        ]
      },
      {
        title: 'Guides',
        children: [
          '/docs/guides/schema-organisation',
          '/docs/guides/relay',
          '/docs/guides/auth',
          '/docs/guides/validation',
          '/docs/guides/relationships',
          '/docs/guides/plugin-development'
        ]
      },
      {
        title: 'API Reference',
        children: [
          '/docs/api-reference/directives',
          '/docs/api-reference/resolvers',
        ]
      }
    ]
  }
}