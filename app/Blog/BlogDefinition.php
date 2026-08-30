<?php

declare(strict_types=1);

namespace ReaCms\Blog;

use ReaCms\Content\ResourceDefinition;

final class BlogDefinition
{
    public static function posts(): ResourceDefinition
    {
        return new ResourceDefinition('blog', 'posts', 'plugin_blog_posts', [
            'title' => 'string', 'slug' => 'string', 'excerpt' => 'text', 'content' => 'text',
            'status' => 'string', 'locale' => 'string', 'visibility' => 'string', 'author_id' => 'integer',
            'featured_media_id' => 'media', 'seo_title' => 'string', 'meta_description' => 'text',
            'canonical_url' => 'string', 'robots' => 'string', 'open_graph' => 'json',
            'structured_data' => 'json', 'featured' => 'boolean', 'pinned' => 'boolean', 'position' => 'integer',
            'publish_at' => 'datetime', 'unpublish_at' => 'datetime', 'created_at' => 'datetime',
            'updated_at' => 'datetime', 'deleted_at' => 'datetime',
        ], ['title', 'slug', 'content', 'status', 'locale'], [
            'blog.posts.create', 'blog.posts.update', 'blog.posts.delete', 'blog.posts.publish',
        ]);
    }
}
