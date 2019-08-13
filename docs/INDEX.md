---
home: true
actionText: Get Started  →
actionLink: /docs/latest

footer: Copyright © 2019 Christopher Moore
---

<div class="home-container">
  <section class="bg-purple-darkest bg-no-repeat bg-cover pb-8" style="background-image:url(bg-hero@2x.png); padding-top:80px;">
    <div class="container py-4">
      <div class="flex flex-wrap items-center py-8">
        <img src="/logo-md.svg" height="170" width="170" class="mx-auto mb-4 sm:mx-0 sm:mb-0 shadow-lg" style="border:none !important;">
        <div class="w-full md:flex-1 px-6">
          <h2 class="text-white text-4xl font-light leading-normal mb-2">Lighthouse</h2>
          <h3 class="text-purple mb-2">GraphQL Server for Laravel</h3>
          <p class="text-sm text-white">Lighthouse is a PHP package that allows you to serve a GraphQL
              endpoint from your Laravel application. It greatly reduces the
              boilerplate required to create a schema, integrates well
              with any Laravel project, and is highly customizable giving
              you full control over your data.</p>
        </div>
        <div class="w-1/5"></div>
      </div>
    </div>
  </section>
  <section class="bg-white">
    <div class="container py-8 px-2">
      <div class="flex flex-wrap justify-between">
        <div class="w-full md:w-1/2 pt-8 pb-8">
          <h3 class="mb-4 text-xl">Rapid GraphQL Development with Laravel</h3>
          <p>Lighthouse dramatically reduces the amount of boilerplate needed to get a GraphQL project up and running. Many of the familiar concepts from Laravel are converted into Lighthouse directives, so you can reuse existing logic and work the way you are used to.</p>
          <p>If you already have your models and migrations set up, it only takes minutes to get up and running with Lighthouse. You only need to define a schema to have a fully functional GraphQL server with no additional code.</p>
          <p>You can extend Lighthouse to fit just about any data requirements. The docs will walk you through what directives are available, how to create your own directives and how to create your own resolvers.</p>
          <a href="/docs/latest.html" @click.prevent="$router.push('/docs/latest.html')" class="button mt-8">Get Started</a>
        </div>
        <div class="w-full md:w-2/5">
          <div class="md:-mt-16 shadow-md">
            <div class="shadow-md">
                <pre class="language-graphql">
<code>type User <span class="token punctuation">{</span>
  <span class="token attr-name">id</span><span class="token punctuation">:</span> ID<span class="token operator">!</span>
  <span class="token attr-name">name</span><span class="token punctuation">:</span> String<span class="token operator">!</span>
  <span class="token attr-name">email</span><span class="token punctuation">:</span> String
  <span class="token attr-name">posts</span><span class="token punctuation">:</span> <span class="token punctuation">[</span>Post<span class="token operator">!</span><span class="token punctuation">]</span><span class="token operator">!</span> <span class="token directive function">@hasMany</span>
<span class="token punctuation">}</span><br/>
type Post <span class="token punctuation">{</span>
  <span class="token attr-name">title</span><span class="token punctuation">:</span> String<span class="token operator">!</span>
  <span class="token attr-name">content</span><span class="token punctuation">:</span> String<span class="token operator">!</span>
  <span class="token attr-name">author</span><span class="token punctuation">:</span> User <span class="token directive function">@belongsTo</span>
<span class="token punctuation">}</span><br/>
type Query <span class="token punctuation">{</span>
  <span class="token attr-name">me</span><span class="token punctuation">:</span> User <span class="token directive function">@auth</span>
  <span class="token attr-name">posts</span><span class="token punctuation">:</span> <span class="token punctuation">[</span>Post<span class="token operator">!</span><span class="token punctuation">]</span><span class="token operator">!</span> <span class="token directive function">@paginate</span>
<span class="token punctuation">}</span><br/>
type Mutation <span class="token punctuation">{</span>
  createPost<span class="token punctuation">(</span>
    <span class="token attr-name">title</span><span class="token punctuation">:</span> String <span class="token directive function">@rules</span><span class="token punctuation">(</span><span class="token attr-name">apply</span><span class="token punctuation">:</span> <span class="token punctuation">[</span><span class="token string">"required"</span><span class="token punctuation">,</span> <span class="token string">"min:2"</span><span class="token punctuation">]</span><span class="token punctuation">)</span>
    <span class="token attr-name">content</span><span class="token punctuation">:</span> String <span class="token directive function">@rules</span><span class="token punctuation">(</span><span class="token attr-name">apply</span><span class="token punctuation">:</span> <span class="token punctuation">[</span><span class="token string">"required"</span><span class="token punctuation">,</span> <span class="token string">"min:12"</span><span class="token punctuation">]</span><span class="token punctuation">)</span>
  <span class="token punctuation">)</span><span class="token punctuation">:</span> Post <span class="token directive function">@create</span>
<span class="token punctuation">}</span></code></pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="bg-grey-lightest">
    <div class="container py-12">
      <h3 class="text-center mb-4 text-2xl">Features</h3>
      <h4 class="text-center text-lg mb-4 font-sans text-grey-dark">Fully customizable to fit your data requirements.</h4>
      <div class="flex flex-wrap pt-6">
        <div class="w-full mb-4 md:w-1/3 md:mb-0 px-4">
          <h4 class="text-purple-darkest text-xl mb-2">📜 Schema First</h4>
          <p class="mb-2">Lighthouse allows you to use the native Schema Definition Language to describe your data. Leverage server-side directives to add functionality and bring your schema to life.</p>
          <a href="/docs/latest/the-basics/schema.html" @click.prevent="$router.push('/docs/latest/the-basics/schema.html')" class="font-bold uppercase text-sm">Read More</a>
        </div>
        <div class="w-full mb-4 md:w-1/3 md:mb-0 px-4">
          <h4 class="text-purple-darkest text-xl mb-2">❤ Laravel Friendly</h4>
          <p class="mb-2">Lighthouse integrates with your Laravel application without the need to re-write your entire domain. Just build a GraphQL schema on top of your current logic and start querying.</p>
          <a href="/docs/latest" @click.prevent="$router.push('/docs/latest.html')" class="font-bold uppercase text-sm">Read More</a>
        </div>
        <div class="w-full md:w-1/3 md:mb-0 px-4">
          <h4 class="text-purple-darkest text-xl mb-2">⚡ Optimized for Eloquent</h4>
          <p class="mb-2">Eloquent is an extremely powerful ORM. Lighthouse leverages your current model relationships and creates optimized database queries.</p>
          <a href="/docs/latest/eloquent/getting-started.html" @click.prevent="$router.push('/docs/latest/eloquent/getting-started.html')" class="font-bold uppercase text-sm">Read More</a>
        </div>
      </div>
    </div>
    <div class="container text-center pb-8">
        <a href="/docs/latest.html" @click.prevent="$router.push('/docs/latest.html')" class="button mt-8">Read the Docs</a>
    </div>
  </section>
</div>
