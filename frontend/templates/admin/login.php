<h1>Admin sign in</h1>
<?php if ($view['error'] !== null): ?>
<p class="alert" role="alert"><?= $e($view['error']) ?></p>
<?php endif; ?>
<form action="/admin/login" method="post">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<label for="username">Username</label>
<input id="username" type="text" name="username" autocomplete="username" maxlength="64" required>
<label for="password">Password</label>
<input id="password" type="password" name="password" autocomplete="current-password" maxlength="4096" required>
<button type="submit">Continue</button>
</form>
