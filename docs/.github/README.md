# Lighthouse Website

The Lighthouse docs website uses [Vue Press](https://vuepress.vuejs.org),
a minimalistic [Vue](https://vuejs.org/) powered static site generator.

## Directory structure

```
docs/
├── .vuepress/
│   ├── config.js         # global site config
│   └── versions.json     # auto-generated versions file
│
├── master/
│   ├── guides/
│   │   └── auth.md       # https://mysite.com/master/guides/auth.html
│   ├── the-basics/
│   │   └── fields.md     # https://mysite.com/master/the-basics/fields.html
│   │
│   └── sidebar.js        # versioned sidebar for this version
│
├── 2.6/
│   └── ...               # same structure as "docs/master/"
│
├── pages/
│   └── ...               # Not versioned, it remains the same for all docs versions
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

Place the new file into the corresponding version folder,
e.g. `docs/3.1/guides/cruds.md`

Include the reference for the new file into the corresponding `sidebar.js`,
according to its version number, e.g. `docs/3.1/sidebar.js`

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

Each subfolder in `docs/` will represent a documentation version,
except `docs/pages/` that will remain the same for all docs versions.

This ensures that the docs are always in sync with the released version of Lighthouse.
Version specific changes are handled by keeping the docs for each version separate.

| Path                                 | Web route                                           |
| ------------------------------------ | --------------------------------------------------- |
| `docs/master/guides/installation.md` | `https://mysite.com/master/guides/installation.html` |
| `docs/2.6/guides/installation.md`    | `https://mysite.com/2.6/guides/installation.html`    |
| `docs/pages/users.md`                | `https://mysite.com/pages/users.html`                |

### Updating existing versions

When you improve the docs, consider if the change you are making applies to
multiple versions of Lighthouse.

Just change the relevant parts of each separate docs folder and commit it all
in a single PR.

### Tagging a new version

1.  First, finish your work on `docs/master`.

1.  Enter a new version number. We only tag minor releases, so `3.1` will get separate
    docs, but `3.1.4` will not.

        yarn bump 3.1

This will copy the contents of `docs/master/` into `docs/<version>/`
and place a new version number in `docs/.vuepress/versions.json`.

## Deployment

The docs are automatically built and deployed on pushes to `master`.
@chrissm79 set up the deployment.

When the docs are down, check https://www.netlifystatus.com for problems on their part.

