# wp-graphql-offset-pagination

Adds traditional offset pagination support to WPGraphQL. This useful only
when you need to implement

-   complex filtering / ordering with custom SQL
-   numbered links to the "pages"

You should not use this plugin if you can avoid it. The cursors in the
wp-graphql core are faster and more efficient. This plugin performs
comparatively to a traditional WordPress.

This plugin implements offset pagination for post object (build-in and custom
ones), content nodes and user connections. PRs welcome for term connections.
See [CONTRIBUTING.md](CONTRIBUTING.md).

This is tested with WPGraphQL 0.6.x.

## Usage

```graphql
query Posts {
    posts(where: { offsetPagination: { size: 10, offset: 10 } }) {
        pageInfo {
            offsetPagination {
                # Get the total node count in the the connection. Using this
                # field activates total calculations which will make your
                # queries slower. Use with caution.
                total
            }
        }
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
