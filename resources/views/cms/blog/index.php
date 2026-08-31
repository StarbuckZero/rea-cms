<?php declare(strict_types=1); /** @var callable(mixed):string $escape */ /** @var list<array<string,mixed>> $posts */ ?>
<section><p class="eyebrow">Content</p><div class="widget-heading mt-3"><h1 class="text-3xl font-bold">Blogs</h1><a class="button-primary" href="/cms/blog/new">New post</a></div>
<div class="panel mt-8"><table class="cms-table"><thead><tr><th>Title</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($posts as $post) : ?><tr><td><strong><?= $escape($post['title']) ?></strong><br><span class="text-sm text-secondary">/blog/<?= $escape($post['slug']) ?></span></td><td><?= $escape($post['status']) ?></td><td><?= $escape($post['updated_at']) ?></td><td><a href="/cms/blog/<?= (int) $post['id'] ?>/edit">Edit</a></td></tr><?php endforeach; ?>
<?php if ($posts === []) : ?><tr><td colspan="4">No blog posts yet.</td></tr><?php endif; ?></tbody></table></div></section>
