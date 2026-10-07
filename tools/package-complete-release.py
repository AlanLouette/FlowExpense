#!/usr/bin/env python3
"""Real accounts and isolated guests at the same URL; excludes all actual data."""
from pathlib import Path
import zipfile
ROOT=Path(__file__).resolve().parents[1]
target=ROOT/'dist/flowexpense-complete-hostinger.zip';target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as z:
    for path in (ROOT/'public').rglob('*'):
        if not path.is_file() or path.is_symlink() or path.name in ['.DS_Store','.htaccess','settings.json'] or path.name.startswith('hosting-') or '__pycache__' in path.parts:continue
        z.write(path,path.relative_to(ROOT/'public'))
    z.write(ROOT/'public/hosting-complete-config.example.php','hosting-config.php')
    z.writestr('.htaccess','DirectoryIndex index.php\n'+(ROOT/'public/.htaccess').read_text())
    for name in ['demo-cleanup.php','change-password.php','backup.php','restore.php']:
        script=(ROOT/'tools'/name).read_text().replace("/../public/lib/demo.php", "/demo.php").replace("/../public/bootstrap.php", "/../bootstrap.php")
        z.writestr('lib/cli-'+name,script)
    assert z.testzip() is None
print(target)
