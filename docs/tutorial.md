# Pushing WPGraphQL Cursor Limits

Although this tutorial is in the `wp-graphql-offset-pagination` repository
this tutorial contains valuable information for any developers extending
WPGraphQL filtering / ordering with just plain WPGraphQL cursors. We'll
discuss what the limits are and how they fall short. We also only use PHP
APIs exposed by WPGraphQL and WP itself.

Here's a limit pushing use case:

You have a custom post type for events and you have the event start time
(timestamp) as a meta field and you want to display the events in this order:

1. First display the events that start today or have started today
2. Then display the events that are the closest to starting
3. Do not show past events at all

The tricky bit is the handling of the events that have been already started
today because they they match to both 1 and 3. In pure MySQL terms this can
be implemented with a clever use of `CASE`, `DATE` and `NOW()`.

This tutorial is on very advanced level. If you get through it and understand
everything I bet you can safely call yourself a "senior WPGraphQL
developer". I assume you known basics of WP development, WP-CLI, SQL and
WPGraphQL.

For purposes of this tutorial we simplify the example a bit so we don't have
to deal with changing time.

## Test data

Lets create some testing data.

Run this with `wp eval-file create-data.php`

```php
foreach (range('A', 'Z') as $num => $char) {
    $num++; // start from 1
    $post_id = wp_insert_post([
        'post_title' => "$char post $num",
        'post_type' => 'post',
        'post_status' => 'publish'
    ]);

    if ($num % 2 === 0) {
        update_post_meta($post_id, 'example', 'Even ' . $char);
    } else {
        update_post_meta($post_id, 'example', 'Odd ' . $char);
    }

    echo "Created $post_id\n";
}
```

This will create a post for each character in the alphabet and saves whether
it's in a even or odd position in the alphabet to `example` meta.

**We will be creating a custom GraphQL Input Field that prioritizes ordering
based on the `example` meta.**

## GraphQL Field for Custom Meta

But first we'll want to expose the `example` meta to the GraphQL schema for
debugging purposes.

```php
add_action(
    'graphql_register_types',
    function () {
        register_graphql_field('Post', 'example', [
            'type' => 'String',
            'resolve' => function (\WPGraphQL\Model\Post $post) {
                return get_post_meta($post->ID, 'example', true);
            }
        ]);
    },
    10,
    0
);
```

We should be now able to query the posts with

```graphql
{
    posts(where: { orderby: { field: TITLE, order: ASC } }) {
        nodes {
            title
            example
        }
    }
}
```

We'll get the posts in the alphabetical order as we asked

```json
{ "title": "A post 1", "example": "Odd A" },
{ "title": "B post 2", "example": "Even B" },
{ "title": "C post 3", "example": "Odd C" },
{ "title": "D post 4", "example": "Even D" },
...
```

## GraphQL Input Field for the Prioritization

Next we'll need to add the input field which can be used to prioritize the
posts. WPGraphQL allows developers to extend the `where` input field. So in
the `graphql_register_types` action we can extend the
`RootQueryToPostConnectionWhereArgs` type. You can find out this type name by
looking it up using [wp-graphiql][].

[wp-graphiql]: https://github.com/wp-graphql/wp-graphiql

```php
add_action(
    'graphql_register_types',
    function () {
        register_graphql_field(
            'RootQueryToPostConnectionWhereArgs',
            'prioritize',
            [
                'type' => 'String'
            ]
        );
    },
    10,
    0
);
```

It's now legal to write

```graphql
{
    posts(where: { prioritize: "Odd" }) {
        nodes {
            title
            example
        }
    }
}
```

## Mapping GraphQL Input field to WP Query

But we must use it for it have any effect. We will use the
`graphql_map_input_fields_to_wp_query` filter to map it into to the query
args of the `\WP_Query` instance WPGraphQL is internally using.

```php
add_filter(
    'graphql_map_input_fields_to_wp_query',
    function (array $query_args, array $where_args) {
        if (!isset($where_args['prioritize'])) {
            // If the "prioritize where is argument is not used, bail out.
            return $query_args;
        }

        // The $query_args is passed to the \WP_Query instance so just copy the
        // value from graphql where args
        $query_args['prioritize'] = $where_args['prioritize'];

        return $query_args;
    },
    10,
    2
);
```

If we were doing something simpler that can be done with straight WP Query we
could just add it to the `$query_args` in a form regonized by it and we would
be done.

For example if we're to just filter out old events we could do this:

```php
$query_args['meta_key'] = 'start_date';
$query_args['meta_query'] = [
    [
        'key' => 'start_date',
        'compare' => '<',
        'value' => time(), // Compare with the current timestamp.
        'type' => 'NUMERIC'
    ]
];
```

WPGraphQL should support all features supported by WP Query. Including
`meta_query` and `tax_query`.

But that's only an "Advanced Level" WPGraphQL usage and this article is about
the "Very Advanced Level" so we'll continue to write some custom SQL 😱

## Generating SQL in the WP Query

Since we only moved the `prioritize` field to a query var that is not
understood by WP Query we must actually teach WP Query how to handle it. We
can do that by hooking in the low level `post_clauses` filter that allow us
to manipulate the SQL query generation inside the WP Query instance.

This were we get into the territory that Cursors cannot handle. Specifically
**because we mess with the `orderby` clause**.

```php
add_filter(
    'posts_clauses',
    function (array $clauses, \WP_Query $query) {
        global $wpdb;

        if (!isset($query->query_vars['prioritize'])) {
            // Bail out if not using the 'prioritize' query var passed from the
            // WPGraphQL filter. NOTE: You should probably use more unique query
            // var name since this hook is called on every \WP_Query usage in
            // WP.
            return $clauses;
        }

        $meta_key = 'example';
        // 🛑 Do not forget to escape user input data!
        $prioritize = esc_sql($query->query_vars['prioritize']);

        // Create join for the meta field. We use a custom alias for the join so
        // we can reference it from the 'fields' clause
        $join_name = 'CUSTOM_META_JOIN';
        $join = " LEFT JOIN $wpdb->postmeta AS $join_name
            ON $wpdb->posts.ID = $join_name.post_id
            AND $join_name.meta_key = '$meta_key' ";

        // Append it to the existing joins
        $clauses['join'] .= $join;

        // Let's add a custom field with alias to the query which can be
        // referenced in ordering. This is the magic. More on this later.
        $field_name = 'PRIORITIZE_ORDER';
        $field = " CASE
            WHEN $join_name.meta_key = '$meta_key'
            AND $join_name.meta_value LIKE '${prioritize}%'
                THEN 1
                ELSE 2
            END AS $field_name";

        // Append it to the fields
        $clauses['fields'] .= ", $field";

        // Make this field the first ordering directive by prepending it
        $clauses['orderby'] = "${field_name}, " . $clauses['orderby'];

        return $clauses;
    },
    10,
    2
);
```

Whoa! That's a lot! But if you got this far you can congratulate youself! You can now write:

```graphql
{
    posts(
        where: { prioritize: "Even", orderby: { field: TITLE, order: ASC } }
    ) {
        nodes {
            title
            example
        }
    }
}
```

and you'll get the "Even" posts first in alphabetical order (BDFHJ...)

```json
{ "title": "B post 2", "example": "Even B" },
{ "title": "D post 4", "example": "Even D" },
{ "title": "F post 6", "example": "Even F" },
...
```

With `wp-graphql-offset-pagination` you can paginate to the "Odd" posts

```graphql
{
    posts(
        where: {
            prioritize: "Even"
            orderby: { field: TITLE, order: ASC }
            offsetPagination: { size: 10, offset: 12 }
        }
    ) {
        nodes {
            title
            example
        }
    }
}
```

and you'll get

```json
{ "title": "Z post 26", "example": "Even Z" },
{ "title": "A post 1", "example": "Odd A" },
{ "title": "C post 3", "example": "Odd C" },
...
```

## Ordering using CASE in SQL

But let's go back to the SQL we just created. Specifically the `CASE` statement:

```sql
CASE
WHEN $join_name.meta_key = '$meta_key' AND $join_name.meta_value LIKE '${prioritize}%'
    THEN 1
    ELSE 2
END
```

This is the magic that allows us to modify the ordering in SQL almost
arbitrarily. With the `CASE` statement we can turn any SQL expression to a
number which can be used in the ORDER statement.

If you still remember the use case I mentioned in the begining, this method
can be used to detect the "current day" and prioritize that.

The `WHEN` statement for it would be something like this

```sql
WHEN DATE( FROM_UNIXTIME( $join_name.meta_value ) ) = DATE( NOW() )
```

This works because the `DATE` type in SQL does not contain the time part and
casting to it just drops it so if it equals to current date it's today!

I'll leave the complete implementation as an exercise to you.

## Cursors?

We're done coding-wise but since I have your attention we'll dive a bit
deeper into the Cursors in WPGraphQL.

You might want to try what happens when you try to paginate the example with
the WPGraphQL cursors (`first`, `after`, `pageInfo.endCursor`). The first
page looks good, maybe the second one too but at some point it goes of the
rails and misses some data.

If you are interested why cursor pagination is a good idea despite of its
limitiations I'd recommend you to read this article from Slack Engineering

<https://slack.engineering/evolving-api-pagination-at-slack-1c1f644f8e12>

tl;dr it's faster on big data sets because with a cursor the database does
not have to read the rows before the cursor at all. Just offseting the query
is a lot more work.

The cursor is implemented as a `WHERE` clause using the auto incremented row
id. So technically the cursor is a post id in the `wp_posts` table. But
**when a `ORDER` clause is added it must be implemented as a cursor too!**

Here's an example of a SQL query with cursors for order by `post_title`,
`modified_date`, `created_date` and `id`:

```sql
WHERE post_title >= $post_title_cursor
      AND ( post_title > $post_title_cursor OR ( post_modified >= $post_modified_cursor
            AND ( post_modified > $post_modified_cursor OR ( post_created >= $post_created_cursor
                AND ( post_created > $post_created_cursor OR id > :$post_id_cursor ) )
            )
        )
    )
ORDER BY post_title, post_modified, post_created, id
```

As you can see it is a recursive problem. You cannot modify this by just
stuffing some extra SQL in the `post_clauses` filter. Also even if you could
you would have to replicate the `CASE` statement in the `WHERE` clause which
would probably destroy the performance gains because `CASE` statement would
need to be evaluated on each row (not 100% sure on this!).

Luckily the cursor builder in WPGraphQL handles this recursive SQL generation
for you for the standard WP Query uses but when you modify the SQL you must
be very careful. But not all modifications are bad. For example **just adding
extra filtering the to the `$fields['where']` should be ok**. For the rest
there is this `wp-graphql-offset-pagination` which enables all the crazy use
cases like this. Albeit beign bit slower.

If you have questions or something to add/correct feel free to ping me on
Twitter [@esamatti][] or open an issue on this repository.

[@esamatti]: https://twitter.com/esamatti
