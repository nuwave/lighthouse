# Client Implementations

To get you up and running quickly, the following sections show how to use subscriptions
with common GraphQL client libraries.

## Apollo for Pusher

To use Lighthouse Pusher subscriptions with the [Apollo](https://www.apollographql.com/docs/react/)
client library you will need to create an `apollo-link`:

```js
import { ApolloLink, Observable } from "apollo-link";

// Inspired by https://github.com/rmosolgo/graphql-ruby/blob/master/javascript_client/src/subscriptions/PusherLink.ts
class PusherLink extends ApolloLink {
  constructor(options) {
    super();

    this.pusher = options.pusher;
  }

  request(operation, forward) {
    const subscribeObservable = new Observable((_observer) => {
      //
    });

    // Capture the super method
    const prevSubscribe = subscribeObservable.subscribe.bind(
      subscribeObservable
    );

    // Override subscribe to return an `unsubscribe` object, see
    // https://github.com/apollographql/subscriptions-transport-ws/blob/master/src/client.ts#L182-L212
    subscribeObservable.subscribe = (observerOrNext, onError, onComplete) => {
      prevSubscribe(observerOrNext, onError, onComplete);

      const observer = getObserver(observerOrNext, onError, onComplete);

      let subscriptionChannel;

      forward(operation).subscribe({
        next: (data) => {
          // If the operation has the subscription channel, it's a subscription
          subscriptionChannel =
            data?.extensions?.lighthouse_subscriptions.channel ?? null;

          // No subscription found in the response, pipe data through
          if (!subscriptionChannel) {
            observer.next(data);
            observer.complete();

            return;
          }

          this.subscribeToChannel(subscriptionChannel, observer);
        },
      });

      // Return an object that will unsubscribe_if the query was a subscription
      return {
        closed: false,
        unsubscribe: () => {
          subscriptionChannel &&
            this.unsubscribeFromChannel(subscriptionChannel);
        },
      };
    };

    return subscribeObservable;
  }

  subscribeToChannel(subscriptionChannel, observer) {
    this.pusher
      .subscribe(subscriptionChannel)
      .bind("lighthouse-subscription", (payload) => {
        if (!payload.more) {
          this.unsubscribeFromChannel(subscriptionChannel);

          observer.complete();
        }

        const result = payload.result;

        if (result) {
          observer.next(result);
        }
      });
  }

  unsubscribeFromChannel(subscriptionChannel) {
    this.pusher.unsubscribe(subscriptionChannel);
  }
}

// Turn `subscribe` arguments into an observer-like thing, see getObserver
// https://github.com/apollographql/subscriptions-transport-ws/blob/master/src/client.ts#L329-L343
function getObserver(observerOrNext, onError, onComplete) {
  if (typeof observerOrNext === "function") {
    // Duck-type an observer
    return {
      next: (v) => observerOrNext(v),
      error: (e) => onError && onError(e),
      complete: () => onComplete && onComplete(),
    };
  } else {
    // Make an object that calls to the given object, with safety checks
    return {
      next: (v) => observerOrNext.next && observerOrNext.next(v),
      error: (e) => observerOrNext.error && observerOrNext.error(e),
      complete: () => observerOrNext.complete && observerOrNext.complete(),
    };
  }
}

export default PusherLink;
```

Then initialize the pusher client and use it in the link stack.

```js
const pusherLink = new PusherLink({
  pusher: new Pusher(PUSHER_API_KEY, {
    cluster: PUSHER_CLUSTER,
    authEndpoint: `${API_LOCATION}/graphql/subscriptions/auth`,
    auth: {
      headers: {
        authorization: BEARER_TOKEN,
      },
    },
  }),
});

const link = ApolloLink.from([pusherLink, httpLink(`${API_LOCATION}/graphql`)]);
```

## Apollo for Laravel Echo

If you are using the Laravel Echo subscription driver with Apollo
you can use [this apollo link](https://github.com/thekonz/apollo-lighthouse-subscription-link).

## Relay Modern

To use Lighthouse's Pusher subscriptions with Relay Modern you will
need to create a custom handler and inject it into Relay's environment.

```js
import Pusher from "pusher-js";
import {
  Environment,
  Network,
  Observable,
  RecordSource,
  Store,
} from "relay-runtime";

const pusherClient = new Pusher(PUSHER_API_KEY, {
  cluster: "us2",
  authEndpoint: `${API_LOCATION}/graphql/subscriptions/auth`,
  auth: {
    headers: {
      authorization: BEARER_TOKEN,
    },
  },
});

const createHandler = (options) => {
  let channelName;
  const { pusher, fetchOperation } = options;

  return (operation, variables, cacheConfig) => {
    return Observable.create((sink) => {
      fetchOperation(operation, variables, cacheConfig)
        .then((response) => {
          return response.json();
        })
        .then((json) => {
          channelName =
            response?.extensions?.lighthouse_subscriptions.channel ?? null;

          if (!channelName) {
            return;
          }

          const channel = pusherClient.subscribe(channelName);

          channel.bind(`lighthouse-subscription`, (payload) => {
            const result = payload.result;

            if (result && result.errors) {
              sink.error(result.errors);
            } else if (result) {
              sink.next({
                data: result.data,
              });
            }

            if (!payload.more) {
              sink.complete();
            }
          });
        });
    }).finally(() => {
      pusherClient.unsubscribe(channelName);
    });
  };
};

const fetchOperation = (operation, variables, cacheConfig) => {
  const bodyValues = {
    variables,
    query: operation.text,
    operationName: operation.name,
  };

  return fetch(`${API_LOCATION}/graphql`, {
    method: "POST",
    opts: {
      credentials: "include",
    },
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: BEARER_TOKEN,
    },
    body: JSON.stringify(bodyValues),
  });
};

const fetchQuery = (operation, variables, cacheConfig) => {
  return fetchOperation(operation, variables, cacheConfig).then((response) => {
    return response.json();
  });
};

const subscriptionHandler = createHandler({
  pusher: pusherClient,
  fetchOperation: fetchOperation,
});

const network = Network.create(fetchQuery, subscriptionHandler);

export const environment = new Environment({
  network,
  store: new Store(new RecordSource()),
});
```
