#!/usr/bin/env python3
"""
Regenerate every bundled translation file without wp-cli.

    python3 tools/i18n/regen.py            # write files, exit 1 if anything is untranslated
    python3 tools/i18n/regen.py --check    # report only, write nothing

What it does (the recipe from the memory note i18n-regeneration.md, as code):

1. POT from PHP and JS via xgettext, plus the plugin header and every
   block.json title/description/keyword WITH the gettext context WordPress
   uses for them ('block title', 'block description', 'block keyword').
2. de_DE (du) and de_DE_formal (Sie) .po: existing translations are the
   translation memory; new or changed strings come from
   tools/i18n/strings.json ({"msgid" or "ctx\\u0004msgid": ["du", "Sie"]}).
   Nothing is ever dropped silently: an untranslated string is listed and
   the run fails.
3. .mo via msgfmt --check-format.
4. JED JSON per editor bundle (build/<block>/index.js, md5 of that path):
   every string with a .js reference that literally occurs in the bundle.

Run `npm run build` first, the JED step reads build/.
"""
import glob
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile

import polib

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
LANG = os.path.join(ROOT, 'languages')
STRINGS = os.path.join(os.path.dirname(__file__), 'strings.json')
CHECK_ONLY = '--check' in sys.argv
VARIANTS = (('de_DE', 0), ('de_DE_formal', 1))
EOT = '\u0004'

KEYWORDS = [
    '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e',
    '_x:1,2c', '_ex:1,2c', 'esc_html_x:1,2c', 'esc_attr_x:1,2c',
    '_n:1,2', '_nx:1,2,4c', '_n_noop:1,2', '_nx_noop:1,2,3c',
]


def key_of(entry):
    return (entry.msgctxt + EOT if entry.msgctxt else '') + entry.msgid


def run_xgettext(files, language, out):
    args = ['xgettext', '--from-code=UTF-8', '--language=' + language, '--add-comments=translators',
            '--no-wrap', '--sort-by-file', '-o', out]
    args += ['--keyword=' + k for k in KEYWORDS]
    subprocess.run(args + files, check=True, cwd=ROOT)


def build_pot():
    skip = ('build/', 'node_modules/', '_wporg-svn/', 'vendor/', 'tests/', 'tools/')
    php = sorted(p for p in glob.glob('**/*.php', root_dir=ROOT, recursive=True) if not p.startswith(skip))
    js = sorted(glob.glob('src/**/*.js', root_dir=ROOT, recursive=True))
    tmp = tempfile.mkdtemp()
    run_xgettext(php, 'PHP', os.path.join(tmp, 'php.pot'))
    run_xgettext(js, 'JavaScript', os.path.join(tmp, 'js.pot'))
    # No --use-first: the .js reference must survive, the JED step needs it.
    subprocess.run(['msgcat', '--no-wrap', '-o', os.path.join(tmp, 'code.pot'),
                    os.path.join(tmp, 'php.pot'), os.path.join(tmp, 'js.pot')], check=True)
    pot = polib.pofile(os.path.join(tmp, 'code.pot'), wrapwidth=0)

    # Plugin header (WordPress translates these without context).
    header = open(os.path.join(ROOT, 'flinkform.php'), encoding='utf-8').read(3000)
    for field in ('Plugin Name', 'Plugin URI', 'Description', 'Author', 'Author URI'):
        m = re.search(r'^\s*\*\s*' + re.escape(field) + r':\s*(.+)$', header, re.M)
        if m and not pot.find(m.group(1).strip()):
            pot.append(polib.POEntry(msgid=m.group(1).strip(), comment=field + ' of the plugin',
                                     occurrences=[('flinkform.php', '')]))

    # block.json strings, with the context the block registry passes.
    for path in sorted(glob.glob('src/*/block.json', root_dir=ROOT)):
        data = json.load(open(os.path.join(ROOT, path), encoding='utf-8'))
        items = []
        if data.get('title'):
            items.append(('block title', data['title']))
        if data.get('description'):
            items.append(('block description', data['description']))
        items += [('block keyword', k) for k in data.get('keywords', [])]
        for ctx, msgid in items:
            # With context (what WordPress looks up since 5.x) and without
            # (older extractors, still in GlotPress history). Cheap, and a
            # missing variant means an English inserter.
            for c in (ctx, None):
                existing = pot.find(msgid, msgctxt=c)
                if existing:
                    if (path, '') not in existing.occurrences:
                        existing.occurrences.append((path, ''))
                else:
                    pot.append(polib.POEntry(msgid=msgid, msgctxt=c, occurrences=[(path, '')]))

    pot.metadata = {
        'Project-Id-Version': 'Flinkform',
        'Report-Msgid-Bugs-To': 'https://flinkform.de/',
        'MIME-Version': '1.0',
        'Content-Type': 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding': '8bit',
        'X-Domain': 'flinkform',
    }
    return pot


def version():
    m = re.search(r"define\(\s*'FLINKFORM_VERSION',\s*'([^']+)'", open(os.path.join(ROOT, 'flinkform.php'), encoding='utf-8').read())
    return m.group(1) if m else ''


def main():
    pot = build_pot()
    extra = json.load(open(STRINGS, encoding='utf-8')) if os.path.exists(STRINGS) else {}
    missing = {}
    outputs = {}

    for variant, idx in VARIANTS:
        old = polib.pofile(os.path.join(LANG, f'flinkform-{variant}.po'))
        memory = {key_of(e): e for e in old if e.msgstr or e.msgstr_plural}
        po = polib.POFile(wrapwidth=0)
        po.metadata = dict(old.metadata)
        po.metadata.update({'Project-Id-Version': 'Flinkform ' + version(), 'Language': variant,
                            'Plural-Forms': 'nplurals=2; plural=(n != 1);'})
        for src in pot:
            e = polib.POEntry(msgid=src.msgid, msgctxt=src.msgctxt, msgid_plural=src.msgid_plural,
                              occurrences=src.occurrences, comment=src.comment, flags=list(src.flags))
            k = key_of(src)
            if k in extra:
                val = extra[k][idx]
                if src.msgid_plural:
                    e.msgstr_plural = {0: val[0], 1: val[1]} if isinstance(val, list) else {0: val, 1: val}
                else:
                    e.msgstr = val
            elif k in memory:
                if src.msgid_plural:
                    e.msgstr_plural = dict(memory[k].msgstr_plural)
                else:
                    e.msgstr = memory[k].msgstr
            else:
                missing.setdefault(k, set()).add(variant)
                if src.msgid_plural:
                    e.msgstr_plural = {0: '', 1: ''}
            po.append(e)
        outputs[variant] = po

    lost = [k for k in {key_of(e) for e in polib.pofile(os.path.join(LANG, 'flinkform-de_DE.po')) if e.msgstr}
            if not any(key_of(e) == k for e in pot)]
    print(f'POT: {len(pot)} strings. Dropped from code since last run: {len(lost)}')

    if missing:
        print(f'\n{len(missing)} UNTRANSLATED (add them to tools/i18n/strings.json):')
        for k, v in missing.items():
            print('  ', json.dumps(k, ensure_ascii=False), sorted(v))

    if CHECK_ONLY:
        sys.exit(1 if missing else 0)

    pot.save(os.path.join(LANG, 'flinkform.pot'))
    for variant, po in outputs.items():
        path = os.path.join(LANG, f'flinkform-{variant}.po')
        po.save(path)
        subprocess.run(['msgfmt', '--check-format', '-o', path[:-3] + '.mo', path], check=True)

        for old_json in glob.glob(os.path.join(LANG, f'flinkform-{variant}-*.json')):
            os.remove(old_json)
        for bundle in sorted(glob.glob('build/*/index.js', root_dir=ROOT)):
            source = open(os.path.join(ROOT, bundle), encoding='utf-8').read()
            messages = {'': {'domain': 'messages', 'lang': variant, 'plural-forms': 'nplurals=2; plural=(n != 1);'}}
            for e in po:
                if not any(o[0].endswith('.js') for o in e.occurrences):
                    continue
                if e.msgid not in source:
                    continue
                value = [e.msgstr_plural[i] for i in sorted(e.msgstr_plural)] if e.msgid_plural else [e.msgstr]
                if not all(value):
                    continue
                messages[key_of(e)] = value
            jed = {'translation-revision-date': '', 'generator': 'tools/i18n/regen.py', 'source': bundle,
                   'domain': 'messages', 'locale_data': {'messages': messages}}
            name = f'flinkform-{variant}-{hashlib.md5(bundle.encode()).hexdigest()}.json'
            with open(os.path.join(LANG, name), 'w', encoding='utf-8') as fh:
                json.dump(jed, fh, ensure_ascii=False, separators=(',', ':'))

    print('Written: POT, 2 x .po/.mo, JED files.')
    sys.exit(1 if missing else 0)


main()
