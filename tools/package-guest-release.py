#!/usr/bin/env python3
"""Package the REAL PHP application, preconfigured for isolated guest workspaces."""
from pathlib import Path
import zipfile
ROOT=Path(__file__).resolve().parents[1]
target=ROOT/'dist/flowexpense-guest-hostinger.zip'
target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as z:
    for path in (ROOT/'public').rglob('*'):
        if not path.is_file() or path.is_symlink() or path.name in ['.DS_Store','.htaccess','hosting-config.php','hosting-config.example.php','hosting-demo-config.example.php','settings.json'] or '__pycache__' in path.parts:continue
        z.write(path,path.relative_to(ROOT/'public'))
    z.write(ROOT/'public/hosting-demo-config.example.php','hosting-config.php')
    # CLI-only maintenance script, under lib (HTTP access denied by .htaccess).
    cleanup=(ROOT/'tools/demo-cleanup.php').read_text().replace("__DIR__ . '/../public/lib/demo.php'","__DIR__ . '/demo.php'")
    z.writestr('lib/demo-cleanup.php',cleanup)
    z.writestr('.htaccess','DirectoryIndex index.php\n'+(ROOT/'public/.htaccess').read_text())
    assert z.testzip() is None
    assert 'index.php' in z.namelist() and 'index.html' not in z.namelist()
print(target)
