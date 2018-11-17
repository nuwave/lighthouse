# Lighthouse Website

Lighthouse website uses [Vue Press](https://vuepress.vuejs.org). A minimalistic [Vue](https://vuejs.org/) powered static site generator.

## Directory structure

```bash
# nuwave/lighthouse repository root

docs/
├── website/              # every markdown file in this folder become a page
│   ├── .vuepress/
│   │   └── config.js 
│   │ 
│   ├── docs/             # nested folders become routes
│   ├── pages/            # just for better organisation
│   └── README.md         # the beauty frontpage
│ 
└── package.json          # vuepress dependencies
└── README.md             # You are reading this


```

## Run

Make sure you have:

- Node 8+
- Yarn 

```bash
# enter docs/ folder
cd docs/

# start vuepress on development mod (with hotreloading)
yarn docs:dev
```


## Creating new files

1. Place the `.md` file on `website/docs/`

2. Insert the url on `website/.vuepress/config.js`

When linkking pages remember to include the `.md` or `.html` extension. Example:

```md
The [@paginate](/docs/api-reference/directives.html#paginate) directive is great!

See more about [fields](/docs/the-basics/fields.md) for more info.

See the [Tutorial](/docs/getting-started/tutorial.html) for more info.
```

