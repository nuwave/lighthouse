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
│   │   └── auth.md       # http://mysite.com/master/guides/auth.html
│   ├── the-basics/         
│   │   └── fields.md     # http://mysite.com/master/the-basics/fields.html
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

Then, start Vue Press on development mode (with hot reloading).

    cd docs/
    yarn
    yarn docs:dev

> Keep a eye on the console when editing pages.
If an error occurs, it might be necessary to restart the compilation process.

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
See the [Tutorial](../getting-started/tutorial.md) for more info.
```

## Versioning

Each subfolder in `docs/` will represent a documentation version,
except `docs/pages/` that will remain the same for all docs versions. 

This ensures that the docs are always in sync with the released version of Lighthouse.
Version specific changes are handled by keeping the docs for each version separate.

| Path                                    | Web route                                           |
|-----------------------------------------|-----------------------------------------------------|
| `docs/master/guides/installation.md`    | `http://mysite.com/master/guides/installation.html` |
| `docs/2.6/guides/installation.md`       | `http://mysite.com/2.6/guides/installation.html`    |
| `docs/pages/users.md`                   | `http://mysite.com/pages/users.html`    |

### Updating existing versions

When you improve the docs, consider if the change you are making applies to
multiple versions of Lighthouse.

Just change the relevant parts of each separate docs folder and commit it all
in a single PR.

### Tagging a new version

1. First, finish your work on `docs/master`.

1. Enter a new version number. We only tag minor releases, so `3.1` will get separate
docs, but `3.1.4` will not.

        yarn docs:version 3.1

This will copy the contents of `docs/master/` into `docs/<version>/`
and place a new version number in `docs/.vuepress/versions.json`.
