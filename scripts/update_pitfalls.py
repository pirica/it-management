import os
import re

DATE_STR = "2026-07-15"
VALID_SUFFIX = f" [Valid]-[{DATE_STR}]"

def parse_agent_notes(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # regex to find pitfalls heading
    heading_rx = re.compile(r'^(\\?##\s+(?:10\.\s+Common\s+Pitfalls|11\.\s+Known\s+Pitfalls|Common\s+Pitfalls|Known\s+Pitfalls))', re.IGNORECASE | re.MULTILINE)
    match = heading_rx.search(content)
    if not match:
        return None

    start_pos = match.start()
    heading_text = match.group(1)

    # find next heading (starts with # or ## followed by space, or escaped versions of them)
    next_heading_rx = re.compile(r'^(\\?##?\s)', re.MULTILINE)
    # search from after the match of pitfalls heading
    next_match = next_heading_rx.search(content, match.end())
    if next_match:
        end_pos = next_match.start()
    else:
        end_pos = len(content)

    before_text = content[:start_pos]
    pitfalls_section = content[start_pos:end_pos]
    after_text = content[end_pos:]

    return {
        'before': before_text,
        'heading': heading_text,
        'pitfalls': pitfalls_section[len(heading_text):], # Keep leading/trailing formatting intact
        'after': after_text
    }

def update_pitfalls_text(text):
    lines = text.splitlines(keepends=True)
    new_lines = []
    # Regex to match list items: starting with optional spaces, bullet point, space, and some content.
    is_item_rx = re.compile(r'^\s*(?:\\?[-*]\s|\d+\.\s+)')

    for line in lines:
        stripped = line.rstrip('\r\n')
        if not stripped:
            new_lines.append(line)
            continue

        # Check if it is a list item/bullet point
        is_item = is_item_rx.match(stripped)

        if is_item:
            # Check if it already has [Valid]-
            if '[Valid]-' not in stripped:
                ending = line[len(stripped):]
                new_lines.append(stripped + VALID_SUFFIX + ending)
            else:
                new_lines.append(line)
        else:
            new_lines.append(line)
    return "".join(new_lines)

def process_file(filepath):
    res = parse_agent_notes(filepath)
    if not res:
        return False

    # Update the pitfalls section text
    updated_pitfalls = update_pitfalls_text(res['pitfalls'])

    # Construct updated file contents
    new_content = res['before'] + res['heading'] + updated_pitfalls + res['after']

    # Write back to file if changed
    with open(filepath, 'r', encoding='utf-8') as f:
        old_content = f.read()

    if new_content != old_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        return True
    return False

def main():
    updated_count = 0
    scanned_count = 0

    # Walk the repository and find all AGENT_NOTES.md files
    for root, dirs, files in os.walk('.'):
        # Skip git and other system/generated directories
        if '.git' in dirs:
            dirs.remove('.git')
        if '.github' in dirs:
            dirs.remove('.github')
        if 'phpunit/coverage' in root:
            continue

        for file in files:
            if file == 'AGENT_NOTES.md':
                scanned_count += 1
                filepath = os.path.join(root, file)
                # Skip the template itself so we don't pollute it
                if 'templates/AGENT_NOTES.md' in filepath:
                    continue
                if process_file(filepath):
                    updated_count += 1
                    print(f"Updated: {filepath}")

    print(f"\nProcessing complete! Scanned {scanned_count} AGENT_NOTES.md files, updated {updated_count} files.")

if __name__ == '__main__':
    main()
