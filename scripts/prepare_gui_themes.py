"""Inventory supplied GUI packs and build reproducible, label-free Grunge crops.

Run with Python + Pillow. Does not change any supplied file or game setting.
All manifest paths are relative to the containing theme directory.
"""
import hashlib
import json
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1] / 'storage/app/gui'
NAMES = ['grunge', 'qiyan', 'rpg1', 'rpg2', 'rpg3']


def save_json(path, value):
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')


def grunge_crops():
    source = Image.open(ROOT / 'grunge/Grunge-UI.png').convert('RGBA')
    out = ROOT / 'grunge/extracted'
    out.mkdir(exist_ok=True)
    recipes = {}
    # Preserve the original crops as references; they contain sample text.
    boxes = {'dialogue': [50, 755, 702, 1033], 'button-normal': [49, 632, 157, 670],
             'button-hover': [49, 684, 157, 723], 'button-accent': [298, 1795, 456, 1838]}
    for name, box in boxes.items():
        source.crop(box).save(out / (name + '-reference.png'))
        recipes[name + '-reference'] = {'source': 'Grunge-UI.png', 'operation': 'crop', 'box': box,
                                      'contains_sample_text': True}
    # Nine clean patches from the panel retain its actual corners/border, while
    # replacing the demonstration form and text with a clean texture sample.
    panel = Image.new('RGBA', (652, 278))
    patches = [([50,755,62,767],[0,0,12,12]), ([62,755,690,767],[12,0,640,12]),
               ([690,755,702,767],[640,0,652,12]), ([50,767,62,1021],[0,12,12,266]),
               ([318,778,326,813],[12,12,640,266]), ([690,767,702,1021],[640,12,652,266]),
               ([50,1021,62,1033],[0,266,12,278]), ([62,1021,690,1033],[12,266,640,278]),
               ([690,1021,702,1033],[640,266,652,278])]
    for src, dst in patches:
        panel.paste(source.crop(src).resize((dst[2]-dst[0], dst[3]-dst[1])), dst[:2])
    panel.save(out / 'dialogue.png')
    recipes['dialogue'] = {'source': 'Grunge-UI.png', 'operation': 'crop_and_assemble',
                          'patches': [{'source_box': a, 'destination_box': b} for a,b in patches],
                          'contains_sample_text': False}
    for name in ['button-normal', 'button-hover', 'button-accent']:
        box = boxes[name]
        image = source.crop(box)
        # Replace only the label band, preserving side edges and top/bottom art.
        band = [12, 10, image.width-12, image.height-8]
        sample = [12, 4, image.width-12, 9]
        image.paste(image.crop(sample).resize((band[2]-band[0],band[3]-band[1])),band[:2])
        image.save(out / (name + '.png'))
        recipes[name] = {'source': 'Grunge-UI.png', 'operation': 'crop_and_replace_label_band',
                         'box': box, 'clean_sample_in_crop': sample, 'replace_box_in_crop': band,
                         'contains_sample_text': False}
    save_json(out / 'crops.json', {'coordinate_system': 'pixels; [left, top, right, bottom), source resolution 750x1951',
                                 'notes': 'Preview has an opaque background. Crops retain it. Clean versions reconstruct texture; PSD layers were not extracted.',
                                 'assets': recipes})


def layer(file, rect=None, source_crop=None):
    result = {'file': file, 'rect': rect or [0, 0, 1, 1]}
    if source_crop:
        result['source_crop'] = source_crop
    return result


def component(layers, text='#f4eee3', padding=None):
    return {'render': 'stretch_layers', 'layers': layers, 'text_color': text,
            'content_padding_px': padding or [18, 22, 18, 22]}


def button(base, hover=None, pressed=None, text='#f4eee3'):
    normal = [layer(base)]
    return {'render': 'stretch_layers', 'text_color': text, 'content_padding_px': [10,18,10,18],
            'min_height_px': 44, 'width': 'text_content', 'states': {
                'normal': {'layers': normal},
                'hover': {'layers': normal + (hover or []), 'css': {} if hover else {'filter': 'brightness(1.15)'}},
                'focus': {'same_as': 'hover'},
                'pressed': {'layers': normal + (pressed or []), 'css': {} if pressed else {'filter': 'brightness(0.8)'}},
                'disabled': {'same_as': 'normal', 'css': {'opacity': 0.45}}}}


def definitions():
    q='Button Small/'
    r='Textures/Buttons/'
    s='Sliced Images/'
    t='Textures/Buttons/Rectangular/Small/'
    g=button('extracted/button-normal.png')
    g['states']['hover']={'layers':[layer('extracted/button-hover.png')]}
    return {
        'grunge': (component([layer('extracted/dialogue.png')], '#e5d3b2'), g,
                   ['Only pack without exported PNG components. Runtime crops are reconstructed from the opaque preview; raw labelled crops are reference-only.']),
        'qiyan': (component([layer('Window/window_c.png')]),
                  button(q+'button_small.png', [layer(q+'button_small_hover.png',[10/358,10/142,338/358,122/142])],
                         [layer(q+'button_small_press.png',[20/358,20/142,318/358,102/142])]),
                  ['Hover/press files are inset overlays, not complete button replacements. Larger ornate button is available in Button Special.']),
        'rpg1': (component([layer('Textures/Dialog/Dialog_Background.png'),
                            layer('Textures/Dialog/Dialog_Border.png',[0,0,1,0.025])]),
                 button(r+'BR_Large_Background.png',[layer(r+'BR_Large_Hover.png',[0,0,1,1])],text='#241e26'),
                 ['Many textures are neutral grayscale; use a dark button label unless tinting is added.',
                  'Dialog_Background is a translucent 16x16 fill; Dialog_Border is an 8x4 strip, used along the top, not stretched over the whole panel.',
                  'BR_Large_Frame is a decorative side bracket, not a complete rectangular frame.']),
        'rpg2': (component([layer(s+'Note Window/notification_container.png')],padding=[24,28,24,28]),
                 button(s+'Smaller Button/sml_button_green.png',
                        [layer(s+'Smaller Button/sml_button_hover.png',[15/282,15/122,252/282,92/122])],
                        [layer(s+'Smaller Button/sml_button_press.png',[15/282,15/122,252/282,92/122])]),
                 ['Notification panel is a complete decorative frame. Smaller Button hover/press are inset overlays.',
                  'Red button variant is supplied; green is the primary action choice.']),
        'rpg3': (component([layer('Textures/Window/Window_Background.png', source_crop=[15,15,113,113])]),
                 button(t+'Buttom_RS_Foreground_Yellow.png', [layer(t+'Buttom_RS_Hover_Yellow.png')]),
                 ['The supplied filename spells Buttom, preserved intentionally.',
                  'Small colored foregrounds contain the full face/frame; matching hover artwork replaces that face at the same bounds.',
                  'No small-button pressed PNG exists; darkening normal is the explicit fallback.',
                  'Window source crop removes transparent shadow padding so text stays inside the visible frame.']),
    }


def main():
    grunge_crops()
    catalog = {'schema_version': 1, 'default_theme': 'text', 'integration_status': 'story_bible_and_shared_player',
               'themes': [{'id':'text','label':'Current text UI','renderer':'existing_css','manifest':None}]}
    overview=Image.new('RGB',(1000,5*200),'#29272c')
    draw=ImageDraw.Draw(overview)
    for row,(name,(dialog,choice,notes)) in enumerate(definitions().items()):
        folder=ROOT/name
        assets=[]
        for p in sorted(folder.rglob('*.png')):
            with Image.open(p) as im:
                assets.append({'file':p.relative_to(folder).as_posix(),'width':im.width,'height':im.height,
                               'has_alpha_channel':'A' in im.getbands(),
                               'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),
                               'usage':'reference_only' if p.parent==folder or p.stem.endswith('-reference') else 'available'})
        manifest={'schema_version':1,'id':name,'label':{'rpg1':'RPG & MMO X','rpg2':'RPG & MMO II','rpg3':'RPG & MMO III'}.get(name,name.title()),
                  'asset_root':'.','renderer':'png','fallback_theme':'text',
                  'resize_policy':'stretch_to_text_bounds',
                  'components':{'dialogue':dialog,'scene_introduction':{'same_as':'dialogue'},
                                'inventory_dialogue':{'same_as':'dialogue'},'choice_button':choice,
                                'close_button':{'same_as':'choice_button','label':'Close'},
                                'selection_indicator':{'renderer':'existing_css','placement':'outside_left','reserved_space_px':24},
                                'inventory_controls':{'renderer':'existing_css'},'audio_controls':{'renderer':'existing_css'}},
                  'notes':notes,'assets':assets,
                  'font_files':[p.relative_to(folder).as_posix() for p in sorted(folder.rglob('*')) if p.suffix.lower() in ['.ttf','.otf']],
                  'extraction_manifest':'extracted/crops.json' if name=='grunge' else None,
                  'source_documents':[p.relative_to(folder).as_posix() for p in sorted(folder.rglob('*.psd'))],
                  'segmented_asset_candidates':[a['file'] for a in assets if any(w in Path(a['file']).stem.lower() for w in ['_middle','_repeat','_mid','_left','_right','_end'])],
                  'segmented_asset_notes':'Candidates are catalogued, not assumed interchangeable. No complete left/middle/right set was found for the selected dialogue or primary button. RPG1 submenu has an explicit left/middle/right border set; RPG3 XP tooltip has left/middle pieces only.'}
        # Validate every referenced image before saving.
        def validate(obj):
            if isinstance(obj,dict):
                if 'file' in obj:
                    assert (folder/obj['file']).is_file(),obj['file']
                    if 'source_crop' in obj:
                        with Image.open(folder/obj['file']) as im:
                            x0,y0,x1,y1=obj['source_crop']
                            assert 0 <= x0 < x1 <= im.width and 0 <= y0 < y1 <= im.height
                for v in obj.values():validate(v)
            elif isinstance(obj,list):
                for v in obj:validate(v)
        validate(manifest['components'])
        save_json(folder/'theme.json',manifest)
        catalog['themes'].append({'id':name,'label':manifest['label'],'renderer':'png','manifest':name+'/theme.json'})
        # Static review shows the exact selected layer recipes stretched to text bounds.
        y=row*200
        draw.text((16,y+10),manifest['label'],fill='white')
        def paint(layers,box):
            x,y,w,h=box
            for l in layers:
                im=Image.open(folder/l['file']).convert('RGBA');rx,ry,rw,rh=l['rect']
                if 'source_crop' in l: im=im.crop(l['source_crop'])
                im=im.resize((max(1,round(w*rw)),max(1,round(h*rh))),Image.Resampling.LANCZOS)
                overview.paste(im,(x+round(w*rx),y+round(h*ry)),im)
        paint(dialog['layers'],(20,y+35,620,100))
        draw.text((45,y+65),'A stone lies beneath the roses. What will you do?',fill=dialog['text_color'])
        paint(choice['states']['normal']['layers'],(660,y+45,140,50))
        draw.text((680,y+62),'Look closer',fill=choice['text_color'])
        paint(choice['states']['hover']['layers'],(830,y+45,140,50))
        draw.text((850,y+62),'Close',fill=choice['text_color'])
        draw.text((660,y+110),'Normal                 Hover',fill='white')
        print(name,len(assets),'PNGs catalogued')
    save_json(ROOT/'themes.json',catalog)
    (ROOT/'review').mkdir(exist_ok=True)
    overview.save(ROOT/'review/selected-components.png')


if __name__=='__main__':main()
