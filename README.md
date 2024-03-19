<div align="center">
  <a href="https://www.lighthouse-php.com">
    <img src="./logo.png" alt=lighthouse-logo" width="150" height="150">
  </a>
</div>

<div align="center">

# Lighthouse

[![Validate](https://github.com/nuwave/lighthouse/workflows/Validate/badge.svg)](https://github.com/nuwave/lighthouse/actions)
[![Code Coverage](https://codecov.io/gh/nuwave/lighthouse/branch/master/graph/badge.svg)](https://codecov.io/gh/nuwave/lighthouse)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)

[![Packagist](https://img.shields.io/packagist/dt/nuwave/lighthouse.svg)](https://packagist.org/packages/nuwave/lighthouse)
[![Latest Stable Version](https://poser.pugx.org/nuwave/lighthouse/v/stable)](https://packagist.org/packages/nuwave/lighthouse)
[![GitHub license](https://img.shields.io/github/license/nuwave/lighthouse.svg)](https://github.com/nuwave/lighthouse/blob/master/LICENSE)

**A framework for serving GraphQL from Laravel**

</div>

Lighthouse is a GraphQL framework that integrates with your Laravel application.
It takes the best ideas of both and combines them to solve common tasks with ease
and offer flexibility when you need it.

## Documentation

The documentation lives at [lighthouse-php.com](https://lighthouse-php.com).

The site includes the latest docs for each major version of Lighthouse.
You can find docs for specific versions by looking at the contents of [/docs/master](/docs/master)
at that point in the git history: `https://github.com/nuwave/lighthouse/tree/<SPECIFIC-TAG>/docs/master`.

## Get involved

- Have a question? [Get your answer using GitHub discussions](https://github.com/nuwave/lighthouse/discussions/new?category=q-a)
- Talk to other users? [Start a discussion](https://github.com/nuwave/lighthouse/discussions/new?category=general)
- Found a bug? [Report a bug](https://github.com/nuwave/lighthouse/issues/new?template=bug_report.md)
- Have an idea? [Propose a feature](https://github.com/nuwave/lighthouse/issues/new?template=feature_proposal.md)
- Want to improve Lighthouse? [Read our contribution guidelines](https://github.com/nuwave/lighthouse/blob/master/CONTRIBUTING.md)

## Versioning

Lighthouse follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Only the current major version receives new features and bugfixes.

Updating between minor versions will not require changes to PHP code or the GraphQL schema
and cause no breaking behavioural changes for consumers of the GraphQL API.
However, only code elements marked with `@api` will remain compatible - all other code in
Lighthouse is considered internal and is thus subject to change.

## Changelog

All notable changes to this project are documented in [`CHANGELOG.md`](CHANGELOG.md).

## Upgrade Guide

When upgrading between major versions of Lighthouse, consider [`UPGRADE.md`](UPGRADE.md).

## Contributing

We welcome contributions of any kind, see how in [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Security Vulnerabilities

If you discover a security vulnerability within Lighthouse,
please email Benedikt Franke via [benedikt@franke.tech](mailto:benedikt@franke.tech).

## Sponsors

Lighthouse is supported by [its awesome sponsors](https://lighthouse-php.com/sponsors).

If you want to support the development of Lighthouse and see your logo there, consider [sponsoring](https://github.com/sponsors/spawnia).
