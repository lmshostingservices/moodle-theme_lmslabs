"""Capture the Help centre screenshots from a running site.

Usage: python3 tools/help_shots.py http://localhost:8503 admin 'Admin123!'
Writes pix/help/<key>.jpg (1440x900 viewport, cropped to the top 600px) for each article in classes/local/help.php.
"""
import re, sys, os
from playwright.sync_api import sync_playwright

base, user, pw = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
src = open(os.path.join(os.path.dirname(__file__), '..', 'classes/local/help.php')).read()
rows = re.findall(r"\['([a-z0-9]+)', '[^']+', '[a-z]+', '[a-z0-9-]+',.*?'(/[^']*)', '[^']*'\],", src, re.S)
out = os.path.join(os.path.dirname(__file__), '..', 'pix', 'help')
os.makedirs(out, exist_ok=True)
with sync_playwright() as p:
    b = p.chromium.launch()
    pg = b.new_context(viewport={'width': 1440, 'height': 900}, device_scale_factor=1).new_page()
    pg.goto(base + '/login/index.php')
    pg.fill('#username', user)
    pg.fill('#password', pw)
    pg.click('#loginbtn')
    for key, path in rows:
        pg.goto(base + path)
        pg.wait_for_timeout(900)
        pg.screenshot(path=os.path.join(out, key + '.jpg'), type='jpeg', quality=82, clip={'x': 0, 'y': 0, 'width': 1440, 'height': 700})
        print(key, path)
    b.close()
