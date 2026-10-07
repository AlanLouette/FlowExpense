#!/usr/bin/env python3
"""Build a deployment archive without personal data, Git history or credentials."""
from pathlib import Path
import zipfile
ROOT=Path(__file__).resolve().parents[1]
target=ROOT/'dist'/'flowexpense-hostinger.zip'
target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as archive:
    for folder in ['public','tools','docs']:
        for path in (ROOT/folder).rglob('*'):
            if not path.is_file() or path.name in ['.DS_Store','hosting-config.php'] or '__pycache__' in path.parts:continue
            archive.write(path,path.relative_to(ROOT))
    for name in ['.env.example','README.md']:
        archive.write(ROOT/name,name)
print(target)
