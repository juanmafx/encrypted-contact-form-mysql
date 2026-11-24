<?php $submission = $view['submission']; ?>
<h1>Submission</h1>
<dl>
<dt>Received (UTC)</dt><dd><?= $e($submission->createdAt->format('Y-m-d H:i:s')) ?></dd>
<dt>Name</dt><dd><?= $e($submission->contact->name) ?></dd>
<dt>Phone</dt><dd><?= $e($submission->contact->phone) ?></dd>
<dt>Email</dt><dd><?= $e($submission->contact->email) ?></dd>
<dt>Message</dt><dd class="message"><?= $e($submission->contact->message) ?></dd>
<dt>Notification</dt><dd><?= $submission->notifiedAt === null ? 'Not delivered' : 'Delivered' ?></dd>
</dl>
<div class="toolbar">
<a href="/admin/submissions">Back to submissions</a>
<form action="/admin/submissions/<?= $e($submission->id) ?>/delete" method="post">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<button type="submit" class="danger">Delete permanently</button>
</form>
</div>
