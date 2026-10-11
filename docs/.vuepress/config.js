const versions = ["master", "6", "5", "4", "3", "2"];
const latest = versions[1];

module.exports = {
  title: "Lighthouse",
  description: "A framework for serving GraphQL from Laravel",
  head: [
    [
      "link",
      {
        rel: "icon",
        href: "/favicon.png",
      },
    ],
    [
      "link",
      {
        rel: "stylesheet",
        type: "text/css",
        href: "https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,800,800i,900,900i",
      },
    ],
    [
      "link",
      {
        rel: "stylesheet",
        type: "text/css",
        href: "https://fonts.googleapis.com/css?family=Miriam+Libre:400,700",
      },
    ],
  ],
  theme: "default-prefers-color-scheme",
  themeConfig: {
    defaultTheme: "light",
    logo: "/logo.svg",
    editLinks: true, //  "Edit this page" at the bottom of each page
    repo: "nuwave/lighthouse", //  Github repo
    docsDir: "docs", //  Github repo docs folder
    latest,
    nav: [
      {
        text: "Docs",
        items: versions.map((version) => ({
          text: version,
          link: `/${version}/getting-started/installation`,
        })),
      },
      {
        text: "Tutorial",
        link: "/tutorial",
      },
      {
        text: "Resources",
        link: "/resources",
      },
      {
        text: "Sponsors",
        link: "/sponsors",
      },
      {
        text: "Changelog",
        link: "https://github.com/nuwave/lighthouse/blob/master/CHANGELOG.md",
      },
      {
        text: "Upgrade Guide",
        link: "https://github.com/nuwave/lighthouse/blob/master/UPGRADE.md",
      },
    ],
    sidebar: versions.reduce(
      (sidebars, version) => ({
        ...sidebars,
        [`/${version}/`]: require(`../${version}/sidebar.js`),
      }),
      {}
    ),
  },
  plugins: [
    ["@vuepress/back-to-top", true],
    ["@vuepress/medium-zoom", true],
    [
      "@vuepress/search",
      {
        searchMaxSuggestions: 10,
        test: `/${latest}/`,
      },
    ],
  ],
};
