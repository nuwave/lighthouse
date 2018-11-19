// TIP this file not work with hot reloading. You must restart server.

// TODO
//   - expcetion if version exists
//   - mandatory argument

const versions = require('../versions.json')

module.exports = {
  versions: {
    get latest () {
      return versions.slice(-1)[0]
    },
    get all () {
      return versions
    }
  },
  get sidebars () {
    let sidebars = {}

    versions.forEach((version) => {
      let sidebar = require(`../../${version}/sidebar.js`)
      sidebars[`/${version}/`] = sidebar
    })

    return sidebars
  },
  linksFor (url) {
    let links = []

    versions.forEach(version => {
      let item = { text: version, link: `/${version}/${url}` }
      links.push(item)
    })

    return links
  }
}


