<?php
/**
 * Type-specific wizard form fields for QR Generator.
 */
$p = $qrPayload ?? [];
$type = $qrSelectedType ?? '';
if ($type === 'website'): ?>
    <div class="form-group"><label for="qr-payload-url">URL</label><input type="url" name="payload[url]" id="qr-payload-url" class="form-control itm-qr-field" required value="<?= sanitize((string)($p['url'] ?? '')) ?>" placeholder="https://example.com"></div>
    <?php
    $qrShowShorten = ($currentMode ?? 'dynamic') === 'dynamic';
    if ($qrShowShorten):
    ?>
    <div class="form-group">
        <label class="itm-checkbox-control">
            <input type="checkbox" name="payload[use_short_url]" value="1" class="itm-qr-field" <?= !empty($p['use_short_url']) || !empty($qrRow['short_url_id']) ? 'checked' : '' ?>>
            <span>Shorten URL with Short URLs</span>
        </label>
    </div>
    <?php endif; ?>
<?php elseif ($type === 'wifi'): ?>
    <div class="form-group"><label>Network name (SSID)</label><input type="text" name="payload[ssid]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['ssid'] ?? '')) ?>"></div>
    <div class="form-group"><label>Password</label><input type="text" name="payload[password]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['password'] ?? '')) ?>"></div>
    <div class="form-group"><label>Encryption</label><select name="payload[encryption]" class="form-control itm-qr-field"><option value="WPA" <?= (($p['encryption'] ?? 'WPA') === 'WPA') ? 'selected' : '' ?>>WPA/WPA2</option><option value="WEP" <?= (($p['encryption'] ?? '') === 'WEP') ? 'selected' : '' ?>>WEP</option><option value="nopass" <?= (($p['encryption'] ?? '') === 'nopass') ? 'selected' : '' ?>>None</option></select></div>
    <div class="form-group"><label class="itm-checkbox-control"><input type="checkbox" name="payload[hidden]" value="1" class="itm-qr-field" <?= !empty($p['hidden']) ? 'checked' : '' ?>><span>Hidden network</span></label></div>
<?php elseif ($type === 'vcard'): ?>
    <div class="form-group"><label>First name</label><input type="text" name="payload[first_name]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['first_name'] ?? '')) ?>"></div>
    <div class="form-group"><label>Last name</label><input type="text" name="payload[last_name]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['last_name'] ?? '')) ?>"></div>
    <div class="form-group"><label>Organization</label><input type="text" name="payload[organization]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['organization'] ?? '')) ?>"></div>
    <div class="form-group"><label>Job title</label><input type="text" name="payload[title]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div class="form-group"><label>Phone</label><input type="text" name="payload[phone]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['phone'] ?? '')) ?>"></div>
    <div class="form-group"><label>Email</label><input type="email" name="payload[email]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['email'] ?? '')) ?>"></div>
    <div class="form-group"><label>Website</label><input type="url" name="payload[website]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['website'] ?? '')) ?>"></div>
    <div class="form-group"><label>Address</label><textarea name="payload[address]" class="form-control itm-qr-field" rows="2"><?= sanitize((string)($p['address'] ?? '')) ?></textarea></div>
<?php elseif ($type === 'email'): ?>
    <div class="form-group"><label>To</label><input type="email" name="payload[to]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['to'] ?? '')) ?>"></div>
    <div class="form-group"><label>Subject</label><input type="text" name="payload[subject]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['subject'] ?? '')) ?>"></div>
    <div class="form-group"><label>Body</label><textarea name="payload[body]" class="form-control itm-qr-field" rows="4"><?= sanitize((string)($p['body'] ?? '')) ?></textarea></div>
<?php elseif ($type === 'phone'): ?>
    <div class="form-group"><label>Phone number</label><input type="text" name="payload[number]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['number'] ?? '')) ?>"></div>
<?php elseif ($type === 'sms'): ?>
    <div class="form-group"><label>Phone number</label><input type="text" name="payload[number]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['number'] ?? '')) ?>"></div>
    <div class="form-group"><label>Message</label><textarea name="payload[message]" class="form-control itm-qr-field" rows="3"><?= sanitize((string)($p['message'] ?? '')) ?></textarea></div>
<?php elseif ($type === 'whatsapp'): ?>
    <div class="form-group"><label>Phone (country code, no +)</label><input type="text" name="payload[number]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['number'] ?? '')) ?>"></div>
    <div class="form-group"><label>Message</label><textarea name="payload[message]" class="form-control itm-qr-field" rows="3"><?= sanitize((string)($p['message'] ?? '')) ?></textarea></div>
<?php elseif ($type === 'facebook' || $type === 'instagram'): ?>
    <div class="form-group"><label>Profile URL</label><input type="url" name="payload[url]" class="form-control itm-qr-field" required value="<?= sanitize((string)($p['url'] ?? '')) ?>" placeholder="https://example.com"></div>
<?php elseif ($type === 'text'): ?>
    <div class="form-group"><label>Text</label><textarea name="payload[text]" class="form-control itm-qr-field" rows="5"><?= sanitize((string)($p['text'] ?? '')) ?></textarea></div>
<?php elseif ($type === 'pdf' || $type === 'mp3'): ?>
    <div class="form-group"><label>Title</label><input type="text" name="payload[title]" class="form-control" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div class="form-group"><label>File</label><input type="file" class="itm-qr-upload" data-target="payload[file_path]" accept="<?= $type === 'pdf' ? '.pdf' : 'audio/*' ?>"><input type="hidden" name="payload[file_path]" id="qr-file-path" value="<?= sanitize((string)($p['file_path'] ?? '')) ?>"><span id="qr-file-label"><?= sanitize((string)($p['file_path'] ?? '')) ?></span></div>
<?php elseif ($type === 'images'): ?>
    <div class="form-group"><label>Gallery title</label><input type="text" name="payload[title]" class="form-control" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div class="form-group"><label>Images</label><input type="file" class="itm-qr-upload-multi" accept="image/*" multiple><div id="qr-images-list"><?php foreach ((array)($p['files'] ?? []) as $fp): ?><input type="hidden" name="payload[files][]" value="<?= sanitize((string)$fp) ?>"><div><?= sanitize((string)$fp) ?></div><?php endforeach; ?></div></div>
<?php elseif ($type === 'video'): ?>
    <div class="form-group"><label>Title</label><input type="text" name="payload[title]" class="form-control" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div class="form-group"><label>Video file</label><input type="file" class="itm-qr-upload" data-target="payload[file_path]" accept="video/*"><input type="hidden" name="payload[file_path]" value="<?= sanitize((string)($p['file_path'] ?? '')) ?>"></div>
    <div class="form-group"><label>Or video URL</label><input type="url" name="payload[url]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['url'] ?? '')) ?>"></div>
<?php elseif ($type === 'apps'): ?>
    <div class="form-group"><label>Title</label><input type="text" name="payload[title]" class="form-control" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div class="form-group"><label>iOS App Store URL</label><input type="url" name="payload[ios_url]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['ios_url'] ?? '')) ?>"></div>
    <div class="form-group"><label>Android Play Store URL</label><input type="url" name="payload[android_url]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['android_url'] ?? '')) ?>"></div>
<?php elseif ($type === 'list_of_links'): ?>
    <div class="form-group"><label>Page title</label><input type="text" name="payload[title]" class="form-control" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div id="qr-links-editor"><?php $links = (array)($p['links'] ?? []); if (!$links) { $links = [['label' => '', 'url' => '' ]]; } foreach ($links as $i => $link): ?>
        <div class="form-group"><input type="text" name="payload[links][<?= (int)$i ?>][label]" placeholder="Label" value="<?= sanitize((string)($link['label'] ?? '')) ?>"><input type="url" name="payload[links][<?= (int)$i ?>][url]" class="itm-qr-field" placeholder="URL" value="<?= sanitize((string)($link['url'] ?? '')) ?>"></div>
    <?php endforeach; ?></div>
<?php elseif ($type === 'menu'): ?>
    <div class="form-group"><label>Menu title</label><input type="text" name="payload[title]" class="form-control" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div class="form-group"><label>Menu JSON (sections with items)</label><textarea name="payload[sections_json]" class="form-control" rows="8"><?= sanitize(json_encode($p['sections'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea><small>Paste JSON array: [{"name":"Starters","items":[{"name":"Soup","price":"5"}]}]</small></div>
<?php elseif ($type === 'business'): ?>
    <div class="form-group"><label>Business name</label><input type="text" name="payload[name]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['name'] ?? '')) ?>"></div>
    <div class="form-group"><label>Description</label><textarea name="payload[description]" class="form-control itm-qr-field" rows="3"><?= sanitize((string)($p['description'] ?? '')) ?></textarea></div>
    <div class="form-group"><label>Phone</label><input type="text" name="payload[phone]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['phone'] ?? '')) ?>"></div>
    <div class="form-group"><label>Email</label><input type="email" name="payload[email]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['email'] ?? '')) ?>"></div>
    <div class="form-group"><label>Website</label><input type="url" name="payload[website]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['website'] ?? '')) ?>"></div>
    <div class="form-group"><label>Address</label><textarea name="payload[address]" class="form-control itm-qr-field" rows="2"><?= sanitize((string)($p['address'] ?? '')) ?></textarea></div>
<?php elseif ($type === 'coupon'): ?>
    <div class="form-group"><label>Coupon title</label><input type="text" name="payload[title]" class="form-control" value="<?= sanitize((string)($p['title'] ?? '')) ?>"></div>
    <div class="form-group"><label>Code</label><input type="text" name="payload[code]" class="form-control itm-qr-field" value="<?= sanitize((string)($p['code'] ?? '')) ?>"></div>
    <div class="form-group"><label>Description</label><textarea name="payload[description]" class="form-control" rows="3"><?= sanitize((string)($p['description'] ?? '')) ?></textarea></div>
    <div class="form-group">
        <label for="qr-payload-expires">Expires (dd/mmm/yyyy)</label>
        <?php itm_render_uk_date_input('payload[expires]', 'qr-payload-expires', $p['expires'] ?? '', ['class' => 'form-control itm-qr-field']); ?>
    </div>
<?php elseif ($type === 'social'): ?>
    <div id="qr-social-editor"><?php $links = (array)($p['links'] ?? []); if (!$links) { $links = [['label' => '', 'url' => '' ]]; } foreach ($links as $i => $link): ?>
        <div class="form-group"><input type="text" name="payload[links][<?= (int)$i ?>][label]" placeholder="Label" value="<?= sanitize((string)($link['label'] ?? '')) ?>"><input type="url" name="payload[links][<?= (int)$i ?>][url]" class="itm-qr-field" placeholder="URL" value="<?= sanitize((string)($link['url'] ?? '')) ?>"></div>
    <?php endforeach; ?></div>
<?php endif; ?>
