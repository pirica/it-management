# Scripts Development Standards

This document defines the rules for creating and updating tools within the `scripts/` directory.

## 1. Catalog Registration
All scripts intended for administrative or developer use must be registered in `scripts/scripts.php`.
- Use the standardized HTML table structure.
- Include appropriate Browser/CLI access badges (`scripts-badge-web`, `scripts-badge-cli`).
- Provide a clear, concise usage description.

## 2. Cross-Environment Output (Newline Standard)
To ensure compatibility between CLI and Browser execution, use a conditional newline string. Hardcoded `\n` or `<br>` are discouraged for generic output.

### Coding Standard:
```php
echo "Message text" . (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
```
Example:
```php
$nl = (php_sapi_name() === 'cli' ? "\n" : "<br><br>");
echo "Verifying User Management IDOR..." . $nl;
```
```php
function colorText($text, $type) {
    $isCli = (php_sapi_name() === 'cli');

    switch ($type) {
        case 'pass':
            return $isCli
                ? "\033[32m$text\033[0m"   // green
                : "<span style='color: green;'>$text</span>";

        case 'fail':
            return $isCli
                ? "\033[31m$text\033[0m"   // red
                : "<span style='color: red;'>$text</span>";

        case 'warn':
            return $isCli
                ? "\033[33m$text\033[0m"   // yellow
                : "<span style='color: goldenrod;'>$text</span>";

        case 'info':
            return $isCli
                ? "\033[34m$text\033[0m"   // blue
                : "<span style='color: dodgerblue;'>$text</span>";

        default:
            return $text;
    }
}
```


## 3. Security & Authentication
- Scripts that perform destructive actions or access sensitive data MUST include role-based access control.
- Check for the 'Admin' role using session variables (e.g., `$_SESSION['role_name']`).
- Use `itm_require_post_csrf()` for all state-changing `POST` requests.
- For CLI scripts, use the `ITM_CLI_SCRIPT` constant to bypass web-specific authentication when appropriate.

## 4. Path Handling
- Always use `dirname(__DIR__)` or `ROOT_PATH` to resolve absolute paths.
- Avoid platform-specific separators; use `DIRECTORY_SEPARATOR` or normalize to forward slashes.

## 5. Verification & Testing
- New scripts should ideally be accompanied by a unit test or a verification PoC.
- Store temporary artifacts created during execution in the `scripts/` directory unless explicitly instructed, register artifacts `scripts/scripts.php` created during execution.

## 6. Retention Rule
- **MANDATORY**: Do not delete existing files in the `scripts/` directory unless explicitly instructed.
