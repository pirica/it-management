import re

with open('modules/todo/index.php', 'r') as f:
    content = f.read()

# 1. Update task list meta
meta_old = r'<\?php if \(\$task\[\"due_date\"\]\): \?>.*?<span>• 📅 <\?php echo date\(\"M j\", strtotime\(\$task\[\"due_date\"\]\)\); \?><\/span>.*?<\?php endif; \?>'
meta_new = \"\"\"                                            <?php if ($task["due_date"]): ?>
                                                <span>• 📅 <?php echo date("M j", strtotime($task["due_date"])); ?></span>
                                            <?php endif; ?>
                                            <?php if ($task["reminder_at"]): ?>
                                                <span title=\"Reminder set\">• 🔔</span>
                                            <?php endif; ?>
                                            <?php if ($task["repeat_pattern"]): ?>
                                                <span title=\"Repeat: <?php echo sanitize($task["repeat_pattern"]); ?>\">• 🔄</span>
                                            <?php endif; ?>\"\"\"

content = re.sub(meta_old, meta_new, content, flags=re.DOTALL)

# 2. Update edit form fields
form_old = r'<div class=\"form-group\">.*?<label>Due Date<\/label>.*?<input type=\"datetime-local\" name=\"due_date\".*?><\/div>'
form_new = \"\"\"                        <div class=\"form-group\">
                            <label>Due Date</label>
                            <input type=\"datetime-local\" name=\"due_date\" value=\"<?php echo isset($data[\"due_date\"]) ? str_replace(\" \", \"T\", substr($data[\"due_date\"], 0, 16)) : \"\"; ?>\">
                        </div>
                        <div class=\"form-group\">
                            <label>Reminder</label>
                            <input type=\"datetime-local\" name=\"reminder_at\" value=\"<?php echo isset($data[\"reminder_at\"]) ? str_replace(\" \", \"T\", substr($data[\"reminder_at\"], 0, 16)) : \"\"; ?>\">
                        </div>
                        <div class=\"form-group\">
                            <label>Repeat Pattern</label>
                            <select name=\"repeat_pattern\">
                                <option value=\"\">-- None --</option>
                                <option value=\"daily\" <?php echo (isset($data[\"repeat_pattern\"]) && $data[\"repeat_pattern\"] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                                <option value=\"weekdays\" <?php echo (isset($data[\"repeat_pattern\"]) && $data[\"repeat_pattern\"] === 'weekdays') ? 'selected' : ''; ?>>On weekdays</option>
                                <option value=\"weekly\" <?php echo (isset($data[\"repeat_pattern\"]) && $data[\"repeat_pattern\"] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                <option value=\"monthly\" <?php echo (isset($data[\"repeat_pattern\"]) && $data[\"repeat_pattern\"] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                <option value=\"annually\" <?php echo (isset($data[\"repeat_pattern\"]) && $data[\"repeat_pattern\"] === 'annually') ? 'selected' : ''; ?>>Annually</option>
                            </select>
                        </div>\"\"\"

content = re.sub(form_old, form_new, content, flags=re.DOTALL)

# 3. Update view table
view_old = r'<\?php if \(\$data\[\"due_date\"\]\): \?>.*?<tr>.*?<th>Due Date<\/th>.*?<td>📅 <\?php echo date\(\"M j, Y H:i\", strtotime\(\$data\[\"due_date\"\]\)\); \?><\/td>.*?<\/tr>.*?<\?php endif; \?>'
view_new = \"\"\"                            <?php if ($data["due_date"]): ?>
                            <tr>
                                <th style=\"text-align: left; padding-right: 20px;\">Due Date</th>
                                <td>📅 <?php echo date("M j, Y H:i", strtotime($data["due_date"])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($data["reminder_at"]): ?>
                            <tr>
                                <th style=\"text-align: left; padding-right: 20px;\">Reminder</th>
                                <td>🔔 <?php echo date("M j, Y H:i", strtotime($data["reminder_at"])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($data["repeat_pattern"]): ?>
                            <tr>
                                <th style=\"text-align: left; padding-right: 20px;\">Repeat</th>
                                <td>🔄 <?php echo sanitize(ucfirst($data["repeat_pattern"])); ?></td>
                            </tr>
                            <?php endif; ?>\"\"\"

content = re.sub(view_old, view_new, content, flags=re.DOTALL)

with open('modules/todo/index.php', 'w') as f:
    f.write(content)
"
