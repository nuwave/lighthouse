# Client Implementations

To get you up and running quickly, the following sections show how to use subscriptions
with common GraphQL client libraries.

## Apollo for Pusher

To use Lighthouse Pusher subscriptions with the [Apollo](https://www.apollographql.com/docs/react)
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
    const prevSubscribe =
      subscribeObservable.subscribe.bind(subscribeObservable);

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
            data?.extensions?.lighthouse_subscriptions.channel;

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
          channelName = response?.extensions?.lighthouse_subscriptions.channel;

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


## Flutter/Dart

To use Lighthouse's Pusher subscriptions with Flutter/Dart GQL libraries like [Ferry](https://ferrygraphql.com), you will
need to create a custom link below:

```dart
import 'dart:async';
import 'dart:convert';

import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:gql_exec/gql_exec.dart';
import 'package:gql_link/gql_link.dart';

typedef WsEventDecoder =
    FutureOr<Map<String, dynamic>?> Function(ChannelReadEvent event);

typedef ChannelNameGetter = String? Function(Response result);

typedef AuthTokenHeadersGetter =
    FutureOr<Map<String, String>> Function(String channelName);

/// A link that handles GraphQL subscriptions using Pusher Channels Protocol.
///
/// Example:
/// ```dart
/// final pusherChannelsLink = PusherChannelsLink(
///   wsHost: 'localhost',
///   wsPort: 8080,
///   appKey: 'your-app-key',
///   scheme: 'ws',
///   eventName: 'lighthouse-subscription',
///   getChannelName: (response) => response.response['extensions']?['lighthouse_subscriptions']?['channel'],
///   authUrl: 'http://localhost:8000/graphql/subscriptions/auth', // optional if you want to use a public channel
///   getAuthToken: (channelName) async { // optional if you want to use a public channel
///     final token = await secureStorage.read(key: 'token');
///
///     if (token == null) {
///       return null;
///     }
///
///     return 'Bearer $token';
///   },
/// );
///
/// final link = Link.from([
///   pusherChannelsLink,
///   HttpLink('http://localhost:8000/graphql'),
/// ]);
/// ```
///
/// Pub dependencies:
/// - [dart_pusher_channels](http://pub.dev/packages/dart_pusher_channels)
/// - [gql_exec](http://pub.dev/packages/gql_exec)
/// - [gql_link](http://pub.dev/packages/gql_link)
///
/// Supported servers:
///
/// - [graphql-ruby](https://graphql-ruby.org/javascript_client/graphiql_subscriptions#pusher) - Pusher & Ably broadcasters.
/// - [lighthouse-php](https://lighthouse-php.com/6/subscriptions/getting-started.html) - Pusher & Laravel Reverb broadcasters.
///
/// Notes:
///
/// Make sure to enable `NoCache` policy for the `subscription` type so you won't be getting old events
/// and this link should always be next to the link that produces the HTTP response.
///
/// And other servers that uses the Pusher Channels Protocol to broadcast GraphQL subscriptions.
class PusherChannelsLink extends Link {
  /// Creates a new [PusherChannelsLink] instance.
  PusherChannelsLink({
    required this.wsHost,
    required this.wsPort,
    required this.appKey,
    required this.scheme,
    required this.eventName,
    required this.getChannelName,
    this.cluster,
    this.authUrl,
    this.getAuthTokenHeaders,
    this.logger,
    this.parser = const ResponseParser(),
    this.wsEventDecoder = _defaultWsEventDecoder,
  }) {
    options = cluster != null
        ? PusherChannelsOptions.fromCluster(
            cluster: cluster!,
            key: appKey,
            host: wsHost,
            port: wsPort,
            scheme: scheme,
            shouldSupplyMetadataQueries: true,
            metadata: PusherChannelsOptionsMetadata.byDefault(),
          )
        : PusherChannelsOptions.fromHost(
            scheme: scheme,
            key: appKey,
            host: wsHost,
            port: wsPort,
            shouldSupplyMetadataQueries: true,
            metadata: PusherChannelsOptionsMetadata.byDefault(),
          );

    _client = PusherChannelsClient.websocket(
      options: options,
      connectionErrorHandler: (exception, stackTrace, refresh) {
        logger?.call('ws link connection error: $exception $stackTrace');

        Future.delayed(const Duration(seconds: 1), refresh);
      },
    );

    _connectionEstablishedSub = _client.onConnectionEstablished.listen((_) {
      logger?.call(
        'ws link connection established: ${_channels.map((e) => e.name).toList()}',
      );

      for (final channel in _channels) {
        channel.subscribeIfNotUnsubscribed();
      }
    });

    _client.connect();
  }

  /// The port of the Pusher server.
  final int wsPort;

  /// The host of the Pusher server.
  final String wsHost;

  /// The app key of the Pusher app.
  final String appKey;

  /// The scheme of the Pusher server (ws or wss).
  final String scheme;

  /// The event name of the Pusher Channels that we should listen to get the GraphQL subscription streams.
  final String eventName;

  /// The function that gets the channel name from the response to determine if we should start a GraphQL subscription.
  final ChannelNameGetter getChannelName;

  /// The cluster of the Pusher app.
  final String? cluster;

  /// The URL of the authentication endpoint that can be used to authenticate the Pusher connection.
  final String? authUrl;

  /// The function that gets the authentication token headers for the authorization URL.
  final AuthTokenHeadersGetter? getAuthTokenHeaders;

  /// The parser function that parses the Pusher Channels response.
  final ResponseParser parser;

  /// The event decoder function that decodes the Pusher Channels event data.
  final WsEventDecoder wsEventDecoder;

  /// The logger function.
  final void Function(String message)? logger;

  /// The list of channels that are subscribed to.
  final List<Channel> _channels = [];

  /// The Pusher client.
  late final PusherChannelsClient _client;

  /// The Pusher options.
  late final PusherChannelsOptions options;

  /// The subscription to the connection established event.
  late final StreamSubscription<void> _connectionEstablishedSub;

  static Map<String, dynamic>? _defaultWsEventDecoder(ChannelReadEvent event) {
    final data = event.data;

    if (data! is String) {
      return null;
    }

    return jsonDecode(data) as Map<String, dynamic>?;
  }

  @override
  Stream<Response> request(Request request, [NextLink? forward]) {
    final controller = StreamController<Response>();

    Channel? channel;

    forward?.call(request).listen((result) async {
      final channelName = getChannelName(result);

      if (channelName is! String) {
        controller.add(result);

        controller.close();

        return;
      }

      final tokenHeaders = authUrl != null
          ? await getAuthTokenHeaders?.call(channelName)
          : null;

      channel =
          (tokenHeaders != null
                  ? _client.privateChannel(
                      channelName,
                      forceCreateNewInstance: true,
                      authorizationDelegate:
                          EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
                            authorizationEndpoint: Uri.parse(authUrl!),
                            headers: tokenHeaders,
                          ),
                    )
                  : _client.publicChannel(
                      channelName,
                      forceCreateNewInstance: true,
                    ))
              as Channel;

      _channels.add(channel!);

      channel!.whenSubscriptionSucceeded().listen((event) {
        logger?.call('ws link subscription succeeded: ${event.data}');
      });

      channel!.onAuthenticationSubscriptionFailed().listen((event) {
        logger?.call('ws link subscription failed: ${event.data}');
      });

      channel!.bind(eventName).listen((event) async {
        final responseBody = await wsEventDecoder(event);

        if (responseBody == null) {
          return;
        }

        final hasMore = responseBody['more'] ?? true;

        final payload = responseBody['result'] ?? <String, dynamic>{};

        final wsResponse = parser.parseResponse(payload);

        if (wsResponse.data != null || wsResponse.errors != null) {
          controller.add(wsResponse);
        }

        if (!hasMore) {
          channel?.unsubscribe();

          await controller.close();

          logger?.call('ws subscription has no more data.');
        }
      }, onError: controller.addError);

      channel!.subscribe();
    }, onError: controller.addError);

    controller.onCancel = () async {
      channel?.unsubscribe();

      logger?.call('ws controller cancelled.');
    };

    return controller.stream;
  }

  @override
  Future<void> dispose() async {
    for (final channel in _channels) {
      channel.unsubscribe();
    }

    _channels.clear();

    await _connectionEstablishedSub.cancel();

    _client.dispose();

    super.dispose();
  }
}
```

