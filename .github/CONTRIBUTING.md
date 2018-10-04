# How to contribute to Lighthouse

Hey, thank you for contributing to Lighthouse. Here are some tips to make
it easy for you.

## Testing

We use **PHPUnit** for unit tests and integration tests.

Have a new feature? You can start off by writing some tests that detail
the behaviour you want to achieve and go from there.

Fixing a bug? The best way to ensure it is fixed for good and never comes
back is to write a failing test for it and then make it pass. If you can
not figure out how to fix it yourself, feel free to submit a PR with a
failing test.  

To run the tests locally, you can use [docker-compose](https://docs.docker.com/compose/install/).
Just clone the project and run the following in the project root:

    docker-compose up -d
    docker-compose exec php sh
    composer install
    composer test

## Committing code

1. Fork the project
1. Create a new branch
1. Write tests
1. Run tests, make sure they fail
1. Write the actual code
1. Commit with a concise title line and a few more lines detailing the change
1. Run tests until they pass. Yay!
1. Open a PR detailing your changes

## Code guidelines

Do not use Facades and utilize dependency injection instead. Not every application has them enabled.

## Code style

We use [StyleCI](https://styleci.io/) to ensure clean formatting, oriented
at the Laravel coding style.

Look through some of the code to get a feel for the naming conventions.

Use type hints and return types whenever possible and make sure to include proper **PHPDocs**

Prefer explicit naming and short functions over excessive comments.

## Documentation

The docs for Lighthouse are maintained in a [seperate repo](https://github.com/nuwave/lighthouse-docs)

Head over there if you want to contribute to the docs, or if you made a PR
here and want to document your changes.
