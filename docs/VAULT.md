1. Workflows for the Vault Key Operations
A. Create Vault Key
When an employee configures their Vault/Master Key for the very first time:

Entry Point: The employee accesses the profile dashboard (user-config.php) and navigates to the Vault Security section (#vault-security).
Inputs Required:
System Password (the standard login password for authentication).
New Master Key and Confirm Master Key.
Verification Process:
The application checks the entered System Password against the hashed password on the employees table using PHP's native password_verify($curr_pw, $current_user['password']).
The system validates that New Master Key === Confirm Master Key.
Hashing and Storage:
Since this is the initial setup, there is no existing data to re-encrypt, so the system bypasses the re-encryption pipeline.
An irreversible bcrypt hash of the Master Key is generated using password_hash($new_vk, PASSWORD_DEFAULT) and stored in the database under employees.vault_key_hash.
Immediate Unlock:
The SHA-256 hash of the plaintext Master Key is calculated: $_SESSION['vault_key'] = hash('sha256', $new_vk).
This unlocks the vault session immediately, allowing the employee to start creating encrypted entries.
B. Unlock Vault
When a session is locked or $_SESSION['vault_key'] is empty, secured modules (Passwords, Bookmarks, Notes, Private Contacts, Todo, and the Private folder in the Explorer) prompt the user with a Vault Locked screen:

Secured Gate:
Opening a secure page (e.g., modules/passwords/index.php or modules/explorer/index.php) triggers a check: empty($_SESSION['vault_key']).
If empty, the application halts normal page rendering and displays the Vault Locked screen modal, requesting the Master Key.
Verification & Authorization:
Upon sending the form, the inputted Master Key is verified against employees.vault_key_hash using password_verify($master_key, $user_data['vault_key_hash']).
Session Key Derivation:
If the password verification succeeds, a deterministic session-key is derived: $_SESSION['vault_key'] = hash('sha256', (string)$master_key).
Data Access (Decryption):
The user is redirected back to the module page.
Database queries fetch the encrypted records, and PHP decrypts them on-the-fly using:
$decrypted = itm_decrypt($row['ciphertext'], $_SESSION['vault_key']);
Plaintext values are then rendered dynamically in the UI.
C. Change Master Key
When an employee updates their Master Key, all previously encrypted records across multiple modules must be re-encrypted with the new key in a single atomic action to prevent data corruption.

Entry Point: This is initiated via the Vault Security section (#vault-security) in user-config.php.
Inputs Required:
System Password (verified against employees.password).
Current Master Key (verified against employees.vault_key_hash).
New Master Key and Confirm Master Key.
Database Transaction (Atomic Safety):
The application begins a database transaction: mysqli_begin_transaction($conn).
Re-encryption Helpers Execution: The system sequentially invokes re-encryption helpers in includes/itm_vault_master_key.php. Each helper loads the employee's private rows, decrypts the fields using the SHA-256 hash of the old key, and re-encrypts them using the SHA-256 hash of the new key:
Passwords: itm_vault_reencrypt_password_entries() decrypts/re-encrypts passwords in the password_entries table.
Bookmarks: itm_vault_reencrypt_bookmark_urls() decrypts/re-encrypts URLs, titles, and notes in the bookmarks and bookmark_folders tables.
Notes: itm_vault_reencrypt_notes() decrypts/re-encrypts titles, content, checklist JSON, and labels in the notes and note_labels tables.
Events: itm_vault_reencrypt_events() decrypts/re-encrypts titles, descriptions, and locations on the events table.
Private Contacts: itm_vault_reencrypt_private_contacts() decrypts/re-encrypts PII fields in private_contacts.
Todo: itm_vault_reencrypt_todo() decrypts/re-encrypts titles and descriptions in the todo table.
Commit or Rollback:
If any re-encryption step fails (e.g., decryption fails due to a corrupted entry, or a database query times out), a PHP Exception is thrown.
The transaction is immediately rolled back using mysqli_rollback($conn), leaving all data securely encrypted with the old key.
If all modules are successfully re-encrypted, the database updates employees.vault_key_hash with the bcrypt hash of the new key, and the transaction is saved via mysqli_commit($conn).
Session Sync:
$_SESSION['vault_key'] is updated with hash('sha256', $new_vk) to keep the current session unlocked seamlessly.
2. If an Employee Forgets Their Vault Key: What Will Happen?
Because the system prioritizes cryptographic privacy and zero-knowledge security for individual employee vault data, a forgotten Vault Key has severe consequences:

A. Complete Cryptographic Lockout
The data is completely unrecoverable: The database stores only the ciphertext (encrypted bytes) and an irreversible bcrypt hash of the master key (used for authentication). The plaintext master key is never stored on the server or in the database.
Without the correct plaintext master key to derive the AES-256 decryption key, the server cannot decrypt the stored data.
Any attempt to use itm_decrypt() with an incorrect or newly generated key will fail to authenticate the ciphertext, resulting in garbage text or boolean false.
B. Impacted Modules and Data Loss
If the key is lost, all data in the following user-scoped areas is permanently compromised and cannot be recovered:

Passwords Module: All account credentials and comments (password_entries).
Notes Module: All private notes content, checklists, and labels (notes, note_labels).
Bookmarks Module: All private bookmarks, folder names, and bookmark notes (bookmarks, bookmark_folders).
Private Contacts Module: All PII fields (phone numbers, addresses, personal emails, photos) in private_contacts.
Todo Module: All private task details and checklists (todo).
Events Module: All private unshared events (events).
Explorer Module: The entire Private/{username}_{employee_id}/ folder in the multi-tenant file system.
C. Can Administrators Help?
No data recovery is possible by administrators: Administrators can reset the employee's standard system login password, but they cannot recover or decrypt any of the vault data, because they also do not have access to the employee's plaintext Master Key.
If an administrator tries to force-reset the vault_key_hash directly in the database:
This allows the employee to set a "new" vault key, but doing so does not re-encrypt the historical data.
The historical data remains encrypted with the forgotten key and will be corrupted/unreadable forever because the decryption function will try to decrypt it with the new key and fail.
D. The Only Remediation Path
If an employee forgets their master key, the only option is to:

Purge/Remove Existing Encrypted Records: The administrator or user must clear all existing encrypted database rows for that specific employee_id to prevent decryption errors or exceptions.
Create a Fresh Vault Key: The user must configure a brand-new Master Key and rebuild their password vault, private notes, bookmarks, and contacts from scratch.
Clean the Explorer Private Folder: The contents of the user's private folder must be cleared so they can upload fresh files under the new master key.
