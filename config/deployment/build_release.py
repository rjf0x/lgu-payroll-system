"""Package reviewed application source and compiled assets without local state."""

import argparse
import hashlib
import json
from pathlib import Path
import subprocess
from datetime import datetime, timezone
import zipfile


def main():
    root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    output = args.output or root / 'storage/app/private/releases' / (
        'lgu-payroll-' + datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S') + '.zip'
    )
    if output.exists():
        parser.error('Output already exists; choose a new release filename.')
    if not (root / 'public/build/manifest.json').is_file() or (root / 'public/hot').exists():
        parser.error('Run npm run build and stop the Vite development server before packaging.')

    files = subprocess.check_output(
        ['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=root
    ).decode().split('\0')
    directories = {'app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'docs'}
    root_files = {'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
                  'vite.config.js', 'README.md', '.env.example', '.env.production.example'}
    selected = set()
    for name in files:
        if not name:
            continue
        path = Path(name)
        if path.parts[0] in directories or name in root_files or (name.startswith('storage/') and path.name == '.gitignore'):
            selected.add(path)
    selected.update(path.relative_to(root) for path in (root / 'public/build').rglob('*') if path.is_file())
    # Runtime bootstrap caches contain configuration secrets and absolute paths.
    selected = {path for path in selected if not str(path).replace('\\', '/').startswith('bootstrap/cache/') or path.name == '.gitignore'}
    output.parent.mkdir(parents=True, exist_ok=True)
    manifest = {'commit': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root).decode().strip(),
                'working_tree_dirty': bool(subprocess.check_output(['git', 'status', '--porcelain'], cwd=root)),
                'created_at': datetime.now(timezone.utc).isoformat(), 'files': {}}
    with zipfile.ZipFile(output, 'x', compression=zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(selected):
            source = root / path
            if source.is_symlink() or not source.resolve().is_relative_to(root):
                raise ValueError('Release sources must be regular files inside the project.')
            if not source.is_file():
                continue
            data = source.read_bytes()
            archive.writestr(path.as_posix(), data)
            manifest['files'][path.as_posix()] = hashlib.sha256(data).hexdigest()
        archive.writestr('release-manifest.json', json.dumps(manifest, indent=2))
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix('.zip.sha256').write_text(digest + '  ' + output.name + '\n', encoding='ascii')
    print(f'Release: {output}\nFiles: {len(manifest["files"])}\nSHA-256: {digest}')


if __name__ == '__main__':
    main()
