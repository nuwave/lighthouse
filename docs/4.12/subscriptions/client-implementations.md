# Client Implementations

To get you up and running quickly, the following sections show how to use subcriptions
with common GraphQL client libraries.

## Apollo

To use Lighthouse subscriptions with the [Apollo](https://www.apollographql.com/docs/react/)
client library you will need to create an `apollo-link`

```js
import { ApolloLink, Observable } from "apollo-link";

class PusherLink extends ApolloLink {
  constructor(options) {
    super();
    // Retain a handle to the Pusher client
    this.pusher = options.pusher;
  }

  request(operation, forward) {
    return new Observable((observer) => {
      // Check the result of the operation
      forward(operation).subscribe({
        next: (data) => {
          // If the operation has the subscription extension, it's a subscription
          const subscriptionChannel = this._getChannel(data, operation);

          if (subscriptionChannel) {
            this._createSubscription(subscriptionChannel, observer);
          } else {
            // No subscription found in the response, pipe data through
            observer.next(data);
            observer.complete();
          }
        },
      });
    });
  }

  _getChannel(data, operation) {
    return !!data.extensions &&
      !!data.extensions.lighthouse_subscriptions &&
      !!data.extensions.lighthouse_subscriptions.channels
      ? data.extensions.lighthouse_subscriptions.channels[
          operation.operationName
        ]
      : null;
  }

  _createSubscription(subscriptionChannel, observer) {
    const pusherChannel = this.pusher.subscribe(subscriptionChannel);
    // Subscribe for more update
    pusherChannel.bind("lighthouse-subscription", (payload) => {
      if (!payload.more) {
        // This is the end, the server says to unsubscribe
        this.pusher.unsubscribe(subscriptionChannel);
        observer.complete();
      }
      const result = payload.result;
      if (result) {
        // Send the new response to listeners
        observer.next(result);
      }
    });
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

## Relay Modern

To use Lighthouse's subscriptions with Relay Modern you will
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
            !!response.extensions &&
            !!response.extensions.lighthouse_subscriptions &&
            !!response.extensions.lighthouse_subscriptions.channels
              ? response.extensions.lighthouse_subscriptions.channels[
                  operation.name
                ]
              : null;

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
