# Lighthouse Website

Lighthouse website uses [Vue Press](https://vuepress.vuejs.org). A minimalistic [Vue](https://vuejs.org/) powered static site generator. VuePress follows the principle of **"convention is better than configuration"**.

## Directory structure

```bash
# nuwave/lighthouse repository root

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
│   └── ...               # same structure from "docs/master/"
│
├── pages/
│   └── ...               # Not versioned, it remains the same for all docs versions
│
├── package.json          # vuepress dependencies
└── INDEX.md              # the beauty home page
```

## Run

Make sure you have:

- Node 8+
- Yarn 

Then, start Vue Press on development mode (with hot reloading).
```bash
cd docs/
yarn install
yarn docs:dev
```

> Keep a eye on console when editing pages. If code can not be compiled for some reason, stop it and start it again Vue Press. Otherwise hot reloading system will hang up.

## Files

### Creating new files

1. Place the new file into correspondent version folder.
2. Include the reference for the new file into correspondent `sidebar.js`, according version number.

```bash
# the new file
docs/3.1/guides/cruds.md

# edit the correspondent sidebar
docs/3.1/sidebar.js

```

### Linking files


- Remember to include the `.md` extension. 
- Always use relative paths according to folder structure.

```md
The [@paginate](directives.md#paginate) directive is great!

See the [Tutorial](../getting-started/tutorial.md) for more info.
```

## Versions

Each subfolder in `docs/` will represent a documentation version, except `docs/pages/` that will remain the same for all docs versions. 

| Path                                    | Web route                                           |
|-----------------------------------------|-----------------------------------------------------|
| `docs/master/guides/installation.md`    | `http://mysite.com/master/guides/installation.html` |
| `docs/2.6/guides/installation.md`       | `http://mysite.com/2.6/guides/installation.html`    |
| `docs/pages/users.md`                   | `http://mysite.com/pages/users.html`    |
  
   
### Updating a existent version

You can update multiples docs versions at the same time. Because each `docs/` subfolder represents specifics routes when published.

1. Edit any file.
1. Commit and push changes.
1. It will be published to the correspondent version.

**Example:** When you change any file from `docs/2.6/`, it will publish changes only for `2.6` docs version.

### Tagging a new version

1. First, finsh your work on `docs/master` . A version always should be based from master.

1. Enter a new version number

```bash
yarn docs:version 3.1
```

1. Commit and push.

1. It will be published as the last documentation version.

When tagging a new version, the document versioning mechanism will:

- Copy full `docs/master/` folder contents into a new `docs/<version>/` folder.
- Place a new version number in `docs/.vuepress/versions.json`






