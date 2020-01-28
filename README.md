# wp-graphql-offset-pagination

Add traditional offset pagination support to WPGraphQL. This useful only when
you need to implement weird custom ordering / filtering which is difficult to
do with the build-in cursor based pagination.

This plugin implements offset pagination for post object (build-in and custom
ones), content nodes and user connections. PRs welcome for term connections.
See [CONTRIBUTING.md](CONTRIBUTING.md).

This is tested with WPGraphQL 0.6.x.

## Usage

```graphql
query Posts {
    posts(where: { offsetPagination: { size: 10, offset: 10 } }) {
        nodes {
            title
        }
    }
}
```

The where argument is the same for `contentNodes` and `users`.

## Prior Art

This a reimplementation of [darylldoyle/wp-graphql-offset-pagination][] by
Daryll Doyle. The API is bit different but this one has unit&integration
tests and support for latest WPGraphQL.

[darylldoyle/wp-graphql-offset-pagination]: https://github.com/darylldoyle/wp-graphql-offset-pagination
