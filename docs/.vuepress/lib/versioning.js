// This file does not work with hot reloading
// Restart the dev server to apply changes

const versions = require('../versions.json')
const fse = require('fs-extra')
const path = process.cwd()

module.exports = {
  versions: {
    // latest stable release
    get latest () {
      return versions[1]
    },
    get all () {
      return versions
    }
  },
  // Generate a single object that represents all versions from each sidebar
  // https://vuepress.vuejs.org/theme/default-theme-config.html#multiple-sidebars
  get sidebars () {
    let sidebars = {}

    versions.forEach((version) => {
      let sidebar = require(`../../${version}/sidebar.js`)
      sidebars[`/${version}/`] = sidebar
    })

    return sidebars
  },
  // Build dropdown items for each version
  linksFor (url) {
    let links = []

    versions.forEach(version => {
      let item = { text: version, link: `/${version}/${url}` }
      links.push(item)
    })

    return links
  },
  // Generate a new version
  generate (version) {
    version = version || process.argv[1]
    console.log('\n')

    if (!fs.existsSync(`${path}/.vuepress/versions.json`)) {
      this.error('File .vuepress/versions.json not found')
    }

    if (typeof version === 'undefined') {
      this.error('No version number specified! \nPass the version you wish to create as an argument.\nEx: 4.4')
    }

    if (versions.includes(version)) {
      this.error(`This version '${version}' already exists! Specify a new version to create that does not already exist.`)
    }

    this.info(`Generating new version into 'docs/${version}' ...`)

    try {
      fse.copySync(`${path}/master`, `${path}/${version}`)

      // remove 'master' from the top of list
      versions.shift()
      // add new generated version on top of list
      versions.unshift(version)
      // add 'master' again on top of list
      versions.unshift('master')

      // write to versions.json

      fs.writeFileSync(
        `${path}/.vuepress/versions.json`,
        `${JSON.stringify(versions, null, 2)}\n`,
      );

      this.success(`Version '${version}' created!`)
    } catch (e) {
      this.error(e)
    }
  },
  error (message) {
    console.log("\x1b[41m%s\x1b[0m", ' ERROR ', `${message}\n`)
    process.exit(0)
  },
  info (message) {
    console.log("\x1b[44m%s\x1b[0m", ' INFO ', `${message}\n`)
  },
  success (message) {
    console.log("\x1b[42m\x1b[30m%s\x1b[0m", ' DONE ', `${message}\n`)
  }
}
