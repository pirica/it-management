import os
import re

def main():
    root_path = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
    modules_dir = os.path.join(root_path, 'modules')
    results = []

    # Single case-insensitive regex with lookaheads to find attributes in any order
    pattern = re.compile(r'<input(?=[^>]*name=["\']active["\'])(?=[^>]*type=["\']text["\'])(?=[^>]*value=["\'][01]["\'])[^>]*>', re.IGNORECASE)

    for module_name in sorted(os.listdir(modules_dir)):
        module_path = os.path.join(modules_dir, module_name)
        if not os.path.isdir(module_path):
            continue

        for filename in ['create.php', 'edit.php']:
            full_path = os.path.join(module_path, filename)
            if os.path.exists(full_path):
                with open(full_path, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()

                    if pattern.search(content):
                        rel_path = os.path.relpath(full_path, root_path).replace('\\', '/')
                        results.append({
                            'path': rel_path,
                            'module': module_name
                        })

    # CLI Output
    print(f"Count: {len(results)}")
    for res in results:
        print(f"{res['path']} (link modules/{res['module']} module)")

    # HTML Output
    html_content = f"""<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Check Non-Standard Active Inputs (Python Result)</title>
    <style>
        body {{ font-family: Consolas, "Courier New", monospace; font-size: 13px; margin: 16px; line-height: 1.4; }}
        pre {{ margin: 0; white-space: pre-wrap; word-break: break-word; }}
        a {{ color: #0969da; text-decoration: none; }}
        a:hover {{ text-decoration: underline; }}
    </style>
</head>
<body>
    <pre>Count: {len(results)}
"""
    for res in results:
        module_url = f"../modules/{res['module']}/index.php"
        html_content += f'<a href="{module_url}" target="_blank">{res["path"]}</a> (<a href="{module_url}" target="_blank">link modules/{res["module"]} module</a>)\n'

    html_content += """</pre>
</body>
</html>"""

    output_file = os.path.join(os.path.dirname(__file__), 'result_check_active_inputs.html')
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(html_content)

if __name__ == "__main__":
    main()
