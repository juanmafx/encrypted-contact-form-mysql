<div class="toolbar">
<h1>Submissions</h1>
<form action="/admin/logout" method="post">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<button type="submit" class="secondary">Sign out</button>
</form>
</div>
<?php if ($view['submissions'] === []): ?>
<p>No submissions on this page.</p>
<?php else: ?>
<table>
<thead><tr><th scope="col">Received (UTC)</th><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Details</th></tr></thead>
<tbody>
<?php foreach ($view['submissions'] as $submission): ?>
<tr>
<td><?= $e($submission->createdAt->format('Y-m-d H:i')) ?></td>
<td><?= $e($submission->contact->name) ?></td>
<td><?= $e($submission->contact->email) ?></td>
<td><a href="/admin/submissions/<?= $e($submission->id) ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<nav class="pager" aria-label="Pagination">
<?php if ($view['page'] > 1): ?><a href="/admin/submissions?page=<?= $view['page'] - 1 ?>">Previous</a><?php endif; ?>
<span>Page <?= $view['page'] ?> of <?= $view['pageCount'] ?></span>
<?php if ($view['page'] < $view['pageCount']): ?><a href="/admin/submissions?page=<?= $view['page'] + 1 ?>">Next</a><?php endif; ?>
</nav>
