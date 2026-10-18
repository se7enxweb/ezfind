// The AJAX search of the ezflow search block (design/standard/javascript/ezajaxsearch.js) against a stubbed ezjscore
// call, in node with mini_dom.js; no browser, database or Solr. One PASS or FAIL line per case, non-zero exit on a
// failure. Run by tests/classes/ezfSearchScriptsTest.php, or alone: node extension/ezfind/tests/js/ajaxsearch.js
'use strict';

const path = require('path');
const { createDocument } = require('./mini_dom.js');

const SCRIPT = path.join(__dirname, '../../design/standard/javascript/ezajaxsearch.js');
let failures = 0;
function check(name, fn) {
    return Promise.resolve().then(fn).then(() => console.log('PASS ' + name), (e) => { failures++; console.log('FAIL ' + name + ': ' + e.message); });
}
function equal(actual, expected, what) {
    if (actual !== expected) throw new Error(what + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual));
}
function ok(value, what) { if (!value) throw new Error(what); }
const tick = () => new Promise((resolve) => setImmediate(resolve));
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

function page() {
    const box = '<div hidden="hidden" data-ezflow-ajaxsearch="1" data-call-url="/index.php/ezjscore/call"'
        + ' data-custom-search-attributes="' + esc(JSON.stringify(['#extra'])) + '"'
        + ' data-noresult-string="' + esc('<p class="none">No search results...</p>') + '"'
        + ' data-search-string="#s" data-search-button="#b" data-search-results="#r" data-spellcheck="#sc a"'
        + ' data-spellcheck-template="' + esc('<div id="sc" class="ajax-search-spellcheck">Did you mean "<a>{spellcheck}</a>"?</div>') + '"'
        + ' data-facets-main-template="' + esc('<div class="ajax-search-facets"><ul>{inner_facet_list}</ul></div>') + '"'
        + ' data-facets-inner-template="' + esc('<li>{facet_name}</li><ul>{facet_element}</ul>') + '"'
        + ' data-facets-element-template="' + esc('<li><a href="{link}">{value}</a> {count}</li>') + '"'
        + ' data-result-template="' + esc('<div class="result-item"><a href="{url_alias}">{title}</a> [{class_name}] {date}</div>') + '"></div>';
    const doc = createDocument('<span id="ezxform_token_js" title="tok"></span><form><input id="s" name="SearchStr" type="text" value="" />'
        + '<input id="b" type="submit" value="Search" /><input id="extra" type="hidden" name="SearchContentClassID" value="2" /></form>'
        + '<div id="r"></div>' + box);
    const requests = [];
    global.window = {
        URLSearchParams,
        setTimeout: (fn) => fn(),
        fetch(url, options) {
            return new Promise((resolve) => requests.push({ url, options, answer: (body) => resolve({ json: () => Promise.resolve(body) }) }));
        },
    };
    global.document = doc;
    delete require.cache[require.resolve(SCRIPT)];
    require(SCRIPT);
    return { doc, requests, results: () => doc.getElementById('r') };
}

(async () => {
    await check('AS-01 the button posts the search to the ezjscore call with the form token and the further fields', () => {
        const p = page();
        p.doc.dispatch('click', p.doc.getElementById('b'), {});
        equal(p.requests.length, 0, 'no search without text');
        p.doc.getElementById('s').value = 'a&b';
        const e = p.doc.dispatch('click', p.doc.getElementById('b'), {});
        ok(e.defaultPrevented, 'the form is not submitted');
        equal(p.requests.length, 1, 'one request');
        equal(p.requests[0].url, '/index.php/ezjscore/call/', 'url');
        equal(p.requests[0].options.method, 'POST', 'method');
        const body = p.requests[0].options.body;
        equal(body.get('SearchStr'), 'a&b', 'text, encoded by URLSearchParams');
        equal(body.get('ezjscServer_function_arguments'), 'ezjsc::search', 'server function');
        equal(body.get('ezxform_token'), 'tok', 'form token');
        equal(body.get('SearchContentClassID'), '2', 'custom attribute');
        equal(body.get('EncodingFormatDate'), 'shortdatetime', 'date format');
        ok(p.results().classList.contains('loading'), 'loading while waiting');
    });

    await check('AS-02 results, facets and spellcheck are rendered with the server values escaped', async () => {
        const p = page();
        p.doc.getElementById('s').value = 'tset';
        const e = p.doc.dispatch('keydown', p.doc.getElementById('s'), { key: 'Enter' });
        ok(e.defaultPrevented, 'Enter searches');
        p.requests[0].answer({ error_text: '', content: {
            SearchResult: [{ name: '<img src=x>Title', url_alias: '/a', class_name: 'Article', published_date: 'today', id: 1, node_id: 2 }],
            SearchExtras: { spellcheck: { collation: 'test' }, facets: [{ name: 'Type', list: [{ url: '/f?x=1', value: 'Article', count: 3 }] }] },
        } });
        await tick();
        const r = p.results();
        ok(!r.classList.contains('loading'), 'loading removed');
        ok(!r.querySelector('img'), 'markup in a title stays text');
        equal(r.querySelector('.result-item a').textContent, '<img src=x>Title', 'title text');
        equal(r.querySelector('.result-item a').getAttribute('href'), '/a', 'link');
        ok(r.querySelector('.ajax-search-facets').classList.contains('is-shown'), 'facets faded in by class');
        equal(r.querySelector('.ajax-search-facets a').getAttribute('href'), '/f?x=1', 'facet link');
        p.doc.dispatch('click', r.querySelector('#sc a'), {});
        equal(p.doc.getElementById('s').value, 'test', 'spellcheck proposal taken');
        equal(p.requests.length, 2, 'and searched');
        p.requests[1].answer({ error_text: '', content: { SearchResult: [], SearchExtras: {} } });
        await tick();
        ok(r.querySelector('p.none'), 'no results string');
    });

    process.exit(failures ? 1 : 0);
})();
