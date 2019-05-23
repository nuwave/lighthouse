# Angular

When using Angular we recommend the package called [Buoy](https://ngx-buoy.com).

The Buoy-client is made with Lighthouse in mind, so pagination, file uploads, etc. work out-of-the-box.
It is based on Apollo and utilizes many of their features.

## Installation

Install via NPM

```sh
npm install @buoy/client
```

After Buoy is installed, you must include it in your application.
Add the `BuoyModule` to your `imports`-section and add the `BuoyConfig` provider in your `providers`-section.

```typescript
import { BuoyModule, BuoyConfig } from '@buoy/client';

const buoyConfig = <BuoyConfig> {
    uri: 'https://demo.ngx-buoy.com/graph' // Demo endpoint.
};

@NgModule({
    declarations: [
        AppComponent
    ],
    imports: [
        BrowserModule,
        BuoyModule
    ],
    providers: [
        { provide: BuoyConfig, useValue: buoyConfig }
    ],
    bootstrap: [AppComponent]
})
export class AppModule {
    constructor() { }
}
```

## Basic usage

All queries are wrapped by a `Query`. These are initialized through Buoy.


```typescript
import { Buoy } from '@buoy/client';

@Component()
export class AppComponent {
    public favouriteMovie: Query;
    
    construct(
        private buoy: Buoy
    ) {
        this.favouriteMovie = this.buoy.query(
            gql `
                query FavouriteMovie($movieId: Int!) {
                    movie(id: $movieId) {
                        title
                        poster
                    }
                }
            `,
            {
                // Variables
                movieId: 10
            },
            {
                // Options
                scope: 'movie'
            }            
        );
    }
}
```

The Query will by default automatically execute the GraphQL query immediately (can be disabled).
Once the query has executed, the scoped data is accessible on `this.favouriteMovie.data`.

```html
<div *ngIf="favouriteMovie.loading">
    Loading ...
</div>
<div *ngif="!favouriteMovie.loading">
    <h1>My Favourite Movie</h1>
    Title: {{ favouriteMovie.data?.title }}<br>
    Poster: <br>
    <!-- Only show the poster if there is one available -->
    <img *ngIf="favouriteMovie.data?.poster !== null"
         [attr.src]="favouriteMovie.data.poster" /> 
</div>
```

For more information, please refer to the [Buoy documentation](https://ngx-buoy.com).