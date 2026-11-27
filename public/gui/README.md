# Game UI skins

This directory contains the runtime theme catalog, manifests, and only the PNGs
used by their components. Commit it with the application. Story Bible previews,
test games, and published games load these themes through `GuiThemes`.

Original design packs, PSDs, unused images, and extraction previews remain in
`storage/app/gui` and are not needed on deployed installations.

After changing the source packs, regenerate their manifests with
`python scripts/prepare_gui_themes.py` (requires Pillow), then publish the selected
assets with `python scripts/export_gui_themes.py` (standard Python only).
