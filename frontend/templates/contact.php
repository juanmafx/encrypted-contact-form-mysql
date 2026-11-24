<h1>Contact form</h1>
<?php if ($view['sent']): ?>
<p class="success" role="status">Your message was sent. We will be in touch with you.</p>
<?php else: ?>
<?php if ($view['notice'] !== null): ?>
<p class="alert" role="alert"><?= $e($view['notice']) ?></p>
<?php endif; ?>
<form action="/contact" method="post" novalidate>
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<div class="trap" aria-hidden="true">
<label for="website">Leave this field empty</label>
<input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
</div>
<?php foreach ([['name', 'Name *', 'text', 'name', 120], ['phone', 'Phone *', 'tel', 'tel', 32], ['email', 'Email *', 'email', 'email', 254]] as [$field, $label, $type, $autocomplete, $maxLength]): ?>
<label for="<?= $field ?>"><?= $label ?></label>
<input id="<?= $field ?>" type="<?= $type ?>" name="<?= $field ?>" autocomplete="<?= $autocomplete ?>" maxlength="<?= $maxLength ?>" required value="<?= $e($view['values'][$field]) ?>"<?= isset($view['errors'][$field]) ? ' class="invalid" aria-invalid="true"' : '' ?>>
<?php if (isset($view['errors'][$field])): ?>
<p class="field-error"><?= $e($view['errors'][$field]) ?></p>
<?php endif; ?>
<?php endforeach; ?>
<label for="message">Message</label>
<textarea id="message" name="message" rows="6" maxlength="5000"<?= isset($view['errors']['message']) ? ' class="invalid" aria-invalid="true"' : '' ?>><?= $e($view['values']['message']) ?></textarea>
<?php if (isset($view['errors']['message'])): ?>
<p class="field-error"><?= $e($view['errors']['message']) ?></p>
<?php endif; ?>
<button type="submit">Send</button>
</form>
<?php endif; ?>
<p class="hint">* Required fields. We will reply to your inquiry shortly.</p>
