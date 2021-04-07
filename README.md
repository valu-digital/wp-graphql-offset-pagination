# wp-graphql-offset-pagination

Adds traditional offset pagination support to WPGraphQL. This is useful only
when you need to implement:

-   Numbered links to the "pages"
-   Ordering with custom SQL
    -   Read the [tutorial](docs/tutorial.md)
    -   You should read it even if don't plan to use this plugin as it teaches
        you a lot about WPGraphQL internals!

**You should not use this plugin if you can avoid it.** The cursors in the
wp-graphql core are faster and more efficient although this plugin should perform
comparatively to a traditional WordPress pagination implementation.

This plugin implements offset pagination for post object (build-in and custom
ones), content nodes and user connections. This means there's no WooCommerce for example
but checkout [this issue](https://github.com/valu-digital/wp-graphql-offset-pagination/issues/1) if you are interested in one.

PRs welcome for term connections. See [CONTRIBUTING.md](CONTRIBUTING.md).



## Usage

```graphql
query Posts {
    posts(where: { offsetPagination: { size: 10, offset: 10 } }) {
        pageInfo {
            offsetPagination {
                # Boolean whether there are more nodes in this connection.
                # Eg. you can increment offset to get more nodes.
                # Use this to implement "fetch more" buttons etc.
                hasMore

                # True when there are previous nodes
                # Eg. you can decrement offset to get previous nodes.
                hasPrevious

                # Get the total node count in the connection. Using this
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

## Installation

Use must have WPGraphQL v0.8.4 or later installed.

If you use composer you can install it from Packagist

    composer require valu/wp-graphql-offset-pagination

Otherwise you can clone it from Github to your plugins using the stable branch

    cd wp-content/plugins
    git clone --branch stable https://github.com/valu-digital/wp-graphql-offset-pagination.git

## Prior Art

This a reimplementation of [darylldoyle/wp-graphql-offset-pagination][] by
Daryll Doyle. The API is bit different but this one has unit&integration
tests and support for latest WPGraphQL.

[darylldoyle/wp-graphql-offset-pagination]: https://github.com/darylldoyle/wp-graphql-offset-pagination
