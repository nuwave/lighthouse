# Lighthouse Website

The Lighthouse docs website uses [Vue Press](https://vuepress.vuejs.org),
a minimalistic [Vue](https://vuejs.org) powered static site generator.

## Directory structure

```
docs/
├── .vuepress/            # https://vuepress.vuejs.org/guide/directory-structure.html
│
├── master/               # Docs for the current version
│   ├── guides/
│   │   └── auth.md       # https://mysite.com/master/guides/auth.html
│   ├── the-basics/
│   │   └── fields.md     # https://mysite.com/master/the-basics/fields.html
│   │
│   └── sidebar.js        # versioned sidebar for this version
│
├── 2/                    # Archived docs for the latest 2.x version, same for other old versions
│   └── ...               # same structure as "docs/master/"
|
├── [x]/                  # Docs for the current stable version, usually a copy of "docs/master/"
│   └── ...               # same structure as "docs/master/"
│
├── pages/                # Pages independent from the version
│
├── package.json          # vuepress dependencies
└── INDEX.md              # the beautiful home page
```

## Development

Make sure you have:

- Node 8+
- Yarn

Then, start Vue Press in development mode (with hot reloading).

    cd docs/
    yarn
    yarn start

> Keep an eye on the console when editing pages.
> If an error occurs, it might be necessary to restart the compilation process.

If you use Docker you can start up the environment (including docs) by running:

    make setup
    make node

Finally, navigate to http://localhost:8081

## Files

### Creating new files

- Place the new file into `master`, e.g. `docs/master/new/feature.md`
- Include the reference to the new file into `docs/master/sidebar.js`

### Linking files

Remember to include the `.md` extension.

```md
The [@paginate](directives.md#paginate) directive is great!
```

Always use relative paths according to folder structure.

```md
See [configuration](../getting-started/configuration.md) for more info.
```

## Versioning

The numbered directories (2, 3, ...) in `docs/` correspond to major releases of Lighthouse,
`docs/pages/` remains the same for all versions.

This ensures that the docs are always in sync with the released version of Lighthouse.
Version specific changes are handled by keeping the docs for each version separate.

| Path                                 | Web route                                            |
| ------------------------------------ | ---------------------------------------------------- |
| `docs/master/guides/installation.md` | `https://mysite.com/master/guides/installation.html` |
| `docs/2/guides/installation.md`      | `https://mysite.com/2/guides/installation.html`      |
| `docs/pages/users.md`                | `https://mysite.com/pages/users.html`                |

### Documenting new features

Add documentation for new features to `docs/master/`.
See [Tagging a new version](#tagging-a-new-version) for how they are released.

### Updating existing versions

When you improve the docs, consider if the change you are making applies to
multiple versions of Lighthouse.

Change the relevant parts of each docs folder and commit it all in a single PR.

### Tagging a new version

After you finished your work on `docs/master/`, copy the updated docs
into the directory of the current major version by running:

    make release

When releasing a new major version, update the version number in the `release` target in [Makefile](../Makefile).

## Deployment

The docs are automatically built and deployed on pushes to `master`.
@chrissm79 set up the deployment.

When the docs are down, check https://www.netlifystatus.com for problems on their part.

