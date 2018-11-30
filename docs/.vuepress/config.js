let versioning = require('./lib/versioning.js')

module.exports = {
  title: 'Lighthouse',
  description: 'GraphQL server for Laravel',
  head: [
    ['link', { rel: 'icon', href: '/favicon.png' }],
    ['link', { rel: 'stylesheet', type: 'text/css', href: 'https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,800,800i,900,900i' },]
  ],
  themeConfig: {
    logo: '/logo.svg',
    editLinks: true,                                //  "Edit this page" at the bottom of each page
    lastUpdated: 'Last Updated',                    //  "Last Updated" at the bottom of each page
    repo: 'nuwave/lighthouse',                      //  Github repo
    docsDir: 'docs/',                               //  Github repo docs folder    
    versions: {
      latest: versioning.versions.latest,
      selected: versioning.versions.latest,
      all: versioning.versions.all
    },
    search: false,
    nav: [
      {
        text: `Docs`,
        items: versioning.linksFor('getting-started/installation.md') // TODO create custom component
      },
      { text: 'Resources', link: '/pages/resources.md' },
      { text: 'Users', link: '/pages/users.md' }
    ],
    sidebar: versioning.sidebars
  }
}
