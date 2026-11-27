"""Publish only runtime theme manifests and their selected PNGs from the source packs."""
import json
import shutil
from pathlib import Path

PROJECT = Path(__file__).resolve().parents[1]
SOURCE = PROJECT / 'storage/app/gui'
DESTINATION = PROJECT / 'public/gui'


def referenced_files(value):
    if isinstance(value, dict):
        if isinstance(value.get('file'), str):
            yield value['file']
        for child in value.values():
            yield from referenced_files(child)
    elif isinstance(value, list):
        for child in value:
            yield from referenced_files(child)


def main():
    catalog = json.loads((SOURCE / 'themes.json').read_text(encoding='utf-8'))
    DESTINATION.mkdir(parents=True, exist_ok=True)
    for theme in catalog['themes']:
        if not theme.get('manifest'):
            continue
        source = SOURCE / theme['id']
        destination = DESTINATION / theme['id']
        manifest = json.loads((source / 'theme.json').read_text(encoding='utf-8'))
        files = set(referenced_files(manifest['components']))
        assets = {asset['file']: asset for asset in manifest['assets']}
        for file in sorted(files):
            path = (source / file).resolve()
            if not path.is_relative_to(source.resolve()) or path.suffix.lower() != '.png':
                raise ValueError(f'Invalid theme asset: {file}')
            asset = assets[file]
            import hashlib
            if hashlib.sha256(path.read_bytes()).hexdigest() != asset['sha256']:
                raise ValueError(f'Asset changed; regenerate source manifest: {file}')
            target = destination / file
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, target)
        manifest['assets'] = [assets[file] for file in sorted(files)]
        for key in ('font_files', 'extraction_manifest', 'source_documents',
                    'segmented_asset_candidates', 'segmented_asset_notes'):
            manifest.pop(key, None)
        (destination / 'theme.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
        print(f"{theme['id']}: {len(files)} runtime PNGs")
    (DESTINATION / 'themes.json').write_text(json.dumps(catalog, indent=2) + '\n', encoding='utf-8')


if __name__ == '__main__':
    main()
