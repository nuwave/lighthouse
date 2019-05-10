<img src="logo.png" width="150" height="150">

# Lighthouse

[![Build Status](https://travis-ci.org/nuwave/lighthouse.svg?branch=master)](https://travis-ci.org/nuwave/lighthouse)
[![codecov](https://codecov.io/gh/nuwave/lighthouse/branch/master/graph/badge.svg)](https://codecov.io/gh/nuwave/lighthouse)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![StyleCI](https://github.styleci.io/repos/59965104/shield?branch=master)](https://github.styleci.io/repos/59965104)
[![Packagist](https://img.shields.io/packagist/dt/nuwave/lighthouse.svg)](https://packagist.org/packages/nuwave/lighthouse)
[![GitHub license](https://img.shields.io/github/license/nuwave/lighthouse.svg)](https://github.com/nuwave/lighthouse/blob/master/LICENSE)
[![Get on Slack](https://img.shields.io/badge/slack-join-orange.svg)](https://join.slack.com/t/lighthouse-php/shared_invite/enQtMzc1NzQwNTUxMjk3LWI1ZDQ1YWM1NmM2MmQ0NTU0NGNjZWFkMTJhY2VjMDAwZmMyZDFlZTc1Mjc3ZGY0MWM1Y2Q5MWNjYmJmYWJkYmU)

**GraphQL Server for Laravel**

Lighthouse is a PHP package that allows you to serve a GraphQL endpoint from your
Laravel application. It greatly reduces the boilerplate required to create a schema,
it integrates well with any Laravel project, and it's highly customizable
giving you full control over your data.

## [Documentation](https://lighthouse-php.com/)

The documentation lives at [lighthouse-php.com](https://lighthouse-php.com/).

If you like reading plain markdown, you can also find the source files in the  [docs folder](/docs).

## Get started

If you have an existing Laravel project, all you really need
to get up and running is a few steps:

1. Install via `composer require nuwave/lighthouse`
2. Publish the default schema `php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=schema`
3. Use something like [GraphQL Playground](https://github.com/mll-lab/laravel-graphql-playground) to explore your GraphQL endpoint

Check out [the docs](https://lighthouse-php.com/) to learn more.

## Get involved

We welcome contributions of any kind.

- Have a question? [Hop into Slack](https://join.slack.com/t/lighthouse-php/shared_invite/enQtMzc1NzQwNTUxMjk3LWI1ZDQ1YWM1NmM2MmQ0NTU0NGNjZWFkMTJhY2VjMDAwZmMyZDFlZTc1Mjc3ZGY0MWM1Y2Q5MWNjYmJmYWJkYmU) 
- Found a bug? [Report a bug](https://github.com/nuwave/lighthouse/issues/new?template=bug_report.md)
- Need a feature? [Open a feature request](https://github.com/nuwave/lighthouse/issues/new?template=feature_request.md)
- Want to improve Lighthouse? [Read our contribution guidelines](https://github.com/nuwave/lighthouse/blob/master/.github/CONTRIBUTING.md)
