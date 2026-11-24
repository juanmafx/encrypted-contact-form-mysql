<h1>Two-factor authentication</h1>
<?php if ($view['error'] !== null): ?>
<p class="alert" role="alert"><?= $e($view['error']) ?></p>
<?php endif; ?>
<form action="/admin/verify" method="post">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<label for="code">Six-digit code from your authenticator app</label>
<input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
<button type="submit">Verify</button>
</form>
