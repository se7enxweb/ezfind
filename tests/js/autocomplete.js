// The search suggestions of ezfind (design/standard/javascript/ezajax_autocomplete.js) against a stubbed server
// function, in node with the small DOM of mini_dom.js; no browser, database or Solr. Prints one PASS or FAIL line per
// case and exits non-zero on a failure. Run by tests/classes/ezfSearchScriptsTest.php, or alone:
//
//   node extension/ezfind/tests/js/autocomplete.js
'use strict';

const path = require('path');
const { createDocument } = require('./mini_dom.js');

const SCRIPT = path.join(__dirname, '../../design/standard/javascript/ezajax_autocomplete.js');
const URL = '/index.php/ezjscore/call/ezfind::autocomplete';
let failures = 0;

function check(name, fn) {
    return Promise.resolve().then(fn).then(() => console.log('PASS ' + name), (e) => { failures++; console.log('FAIL ' + name + ': ' + e.message); });
}
function equal(actual, expected, what) {
    if (actual !== expected) throw new Error(what + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual));
}
function ok(value, what) { if (!value) throw new Error(what); }
const tick = () => new Promise((resolve) => setImmediate(resolve));

// A page with one search field, a fake clock and a stubbed fetch() whose answers the case controls
function page(conf, extraHtml) {
    const attr = JSON.stringify(Object.assign({ url: URL, minQueryLength: 2, resultLimit: 10 }, conf || {})).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    const doc = createDocument('<form id="f" action="/content/search"><div class="ez-autocomplete"><label for="q">Search for</label>'
        + '<input id="q" type="text" name="SearchText" data-ezfind-autocomplete="' + attr + '" /></div></form>' + (extraHtml || ''));
    const timers = [];
    const requests = [];
    const win = {
        setTimeout(fn, ms) { timers.push({ fn, ms }); return timers.length; },
        clearTimeout(id) { if (timers[id - 1]) timers[id - 1].fn = null; },
        AbortController: class { constructor() { this.signal = {}; } abort() { this.signal.aborted = true; } },
        fetch(url, options) {
            return new Promise((resolve, reject) => {
                requests.push({ url, options, answer(body, status) {
                    resolve({ ok: (status || 200) < 400, status: status || 200, json: () => Promise.resolve(body) });
                }, fail() { reject(new Error('network')); } });
            });
        },
    };
    global.window = win;
    global.document = doc;
    delete require.cache[require.resolve(SCRIPT)];
    require(SCRIPT);
    const input = doc.getElementById('q');
    return {
        doc, win, input, requests, timers,
        type(text) { input.value = text; doc.dispatch('input', input, {}); },
        key(key, extra) { return doc.dispatch('keydown', input, Object.assign({ key }, extra || {})); },
        flush() { const due = timers.splice(0); due.forEach((t) => t.fn && t.fn()); },
        list() { return doc.getElementById('q-ezfind-suggestions'); },
        options() { return this.list().querySelectorAll('[role="option"]'); },
        status() { return doc.querySelector('.ezfind-ac-status'); },
    };
}

const ANSWER = { error_text: '', content: [['apple', 3], ['application', 2], ['<b>bad</b> app', 1]] };

(async () => {
    await check('AC-01 the field becomes a combobox with a named listbox and a status region, once', () => {
        const p = page();
        const list = p.list();
        ok(list, 'the listbox exists');
        equal(p.input.getAttribute('role'), 'combobox', 'role');
        equal(p.input.getAttribute('aria-expanded'), 'false', 'aria-expanded');
        equal(p.input.getAttribute('aria-controls'), list.id, 'aria-controls');
        equal(p.input.getAttribute('aria-autocomplete'), 'list', 'aria-autocomplete');
        equal(p.input.getAttribute('autocomplete'), 'off', 'browser autocomplete');
        equal(list.getAttribute('role'), 'listbox', 'listbox role');
        equal(list.getAttribute('aria-label'), 'Search for', 'listbox name from the label');
        ok(list.hidden, 'the listbox starts hidden');
        equal(p.status().getAttribute('role'), 'status', 'status role');
        ok(p.input.parentNode.classList.contains('ezfind-ac-anchor'), 'the wrapper is the positioning anchor');
        equal(list.parentNode.childNodes.indexOf(list), list.parentNode.childNodes.indexOf(p.input) + 1, 'the listbox follows the field');
        equal(window.ezfindAutocomplete.setup().length, 0, 'a second setup attaches nothing');
        equal(p.doc.querySelectorAll('.ezfind-ac-list').length, 1, 'one listbox');
    });

    await check('AC-02 typing is debounced, and the lookup follows the server function contract', () => {
        const p = page({ subtree: 2, classes: 'article,folder' });
        p.type('a');
        p.flush();
        equal(p.requests.length, 0, 'no lookup below minQueryLength');
        p.type('ap'); p.type('app');
        equal(p.timers.filter((t) => t.fn).length, 1, 'one pending lookup');
        equal(p.timers.filter((t) => t.fn)[0].ms, 250, 'default delay');
        p.flush();
        equal(p.requests.length, 1, 'one lookup for the pause');
        equal(p.requests[0].url, URL + '::app::10::2::article%2Cfolder?ContentType=json', 'url');
        equal(p.requests[0].options.method, 'GET', 'method');
        equal(p.requests[0].options.headers.Accept, 'application/json', 'accept');
        const q = page();
        q.type('a b:c');
        q.flush();
        equal(q.requests[0].url, URL + '::a%20b%3Ac::10::::?ContentType=json', 'text encoded, no subtree or classes');
    });

    await check('AC-03 suggestions are rendered as options with the typed text marked, escaped, and announced', async () => {
        const p = page();
        p.type('app'); p.flush();
        p.requests[0].answer(ANSWER);
        await tick();
        const options = p.options();
        equal(options.length, 3, 'options');
        equal(options[0].id, 'q-ezfind-suggestions-0', 'option id');
        equal(options[0].getAttribute('aria-selected'), 'false', 'nothing selected');
        equal(options[1].textContent, 'application', 'text');
        equal(options[1].querySelector('mark').textContent, 'app', 'marked match');
        ok(!options[2].querySelector('b'), 'markup in a suggestion stays text');
        ok(options[2].innerHTML.indexOf('&lt;b&gt;') !== -1, 'escaped markup');
        equal(p.input.getAttribute('aria-expanded'), 'true', 'expanded');
        ok(!p.list().hidden, 'list shown');
        equal(p.status().textContent, '3 suggestions', 'status');
        equal(p.input.getAttribute('aria-activedescendant'), null, 'no active option yet');
    });

    await check('AC-04 arrow keys move through the options and wrap, Enter takes the active one', async () => {
        const p = page({ statusText: 'Vorschläge: %count' });
        p.type('app'); p.flush();
        p.requests[0].answer(ANSWER);
        await tick();
        equal(p.status().textContent, 'Vorschläge: 3', 'translated status');
        let e = p.key('ArrowDown');
        ok(e.defaultPrevented, 'ArrowDown handled');
        equal(p.input.getAttribute('aria-activedescendant'), 'q-ezfind-suggestions-0', 'first active');
        equal(p.options()[0].getAttribute('aria-selected'), 'true', 'aria-selected');
        ok(p.options()[0].classList.contains('is-active'), 'is-active class');
        p.key('ArrowDown'); p.key('ArrowDown');
        equal(p.input.getAttribute('aria-activedescendant'), 'q-ezfind-suggestions-2', 'third active');
        p.key('ArrowDown');
        equal(p.input.getAttribute('aria-activedescendant'), 'q-ezfind-suggestions-0', 'wraps to the first');
        p.key('ArrowUp');
        equal(p.input.getAttribute('aria-activedescendant'), 'q-ezfind-suggestions-2', 'wraps to the last');
        equal(p.options()[0].getAttribute('aria-selected'), 'false', 'only one selected');
        p.key('ArrowUp');
        e = p.key('Enter');
        ok(e.defaultPrevented, 'Enter on an option does not submit');
        equal(p.input.value, 'application', 'value taken');
        equal(p.input.getAttribute('aria-expanded'), 'false', 'closed');
        ok(p.list().hidden, 'list hidden');
        equal(p.input.getAttribute('aria-activedescendant'), null, 'no active descendant');
        e = p.key('Enter');
        ok(!e.defaultPrevented, 'Enter without a list submits the search');
        e = p.key('ArrowDown');
        ok(e.defaultPrevented, 'ArrowDown on a closed list');
        equal(p.input.getAttribute('aria-expanded'), 'true', 'ArrowDown reopens for the same text');
        e = p.key('ArrowDown', { altKey: true });
        equal(p.input.getAttribute('aria-activedescendant'), 'q-ezfind-suggestions-0', 'Alt+ArrowDown does not move');
    });

    await check('AC-05 Escape closes the list, Enter without an active option submits, blur closes', async () => {
        const p = page();
        p.type('app'); p.flush();
        p.requests[0].answer(ANSWER);
        await tick();
        p.key('ArrowDown');
        let e = p.key('Escape');
        ok(e.defaultPrevented, 'Escape handled while open');
        equal(p.input.getAttribute('aria-expanded'), 'false', 'closed');
        equal(p.input.value, 'app', 'text kept');
        e = p.key('Escape');
        ok(!e.defaultPrevented, 'Escape on a closed list is left to the page');
        p.key('ArrowDown');
        equal(p.input.getAttribute('aria-expanded'), 'true', 'reopened');
        p.key('ArrowUp'); p.key('ArrowDown'); p.key('ArrowDown'); p.key('ArrowDown');
        // back to no active option is not possible by arrows; close and reopen without moving
        p.key('Escape'); p.key('ArrowDown', { altKey: true });
        e = p.key('Enter');
        ok(!e.defaultPrevented, 'Enter without an active option submits');
        equal(p.input.getAttribute('aria-expanded'), 'false', 'and closes the list');
        p.key('ArrowDown');
        p.doc.dispatch('blur', p.input, { bubbles: false });
        equal(p.input.getAttribute('aria-expanded'), 'false', 'blur closes');
    });

    await check('AC-06 a click on a suggestion takes it and keeps the focus in the field', async () => {
        const p = page();
        p.type('app'); p.flush();
        p.requests[0].answer(ANSWER);
        await tick();
        const mark = p.options()[1].querySelector('mark');
        const down = p.doc.dispatch('mousedown', mark, {});
        ok(down.defaultPrevented, 'mousedown does not take the focus away');
        p.doc.dispatch('click', mark, {});
        equal(p.input.value, 'application', 'value taken');
        equal(p.doc.activeElement, p.input, 'focus in the field');
        equal(p.input.getAttribute('aria-expanded'), 'false', 'closed');
    });

    await check('AC-07 only the answer to the latest lookup is shown', async () => {
        const p = page();
        p.type('ap'); p.flush();
        p.type('app'); p.flush();
        equal(p.requests.length, 2, 'two lookups');
        ok(p.requests[0].options.signal.aborted, 'the first request is aborted');
        p.requests[1].answer({ error_text: '', content: [['apple', 1]] });
        await tick();
        p.requests[0].answer(ANSWER);
        await tick();
        equal(p.options().length, 1, 'the late first answer is ignored');
        equal(p.options()[0].textContent, 'apple', 'the latest answer');
        p.type('ap'); p.flush();
        equal(p.requests.length, 2, 'an earlier answer for the same text comes from the cache');
        equal(p.options().length, 3, 'cached suggestions rendered');
    });

    await check('AC-08 no suggestions, server errors and a negative minQueryLength', async () => {
        let p = page({ noResultsText: 'Keine Vorschläge' });
        p.type('zz'); p.flush();
        p.requests[0].answer({ error_text: '', content: [] });
        await tick();
        equal(p.input.getAttribute('aria-expanded'), 'false', 'nothing to show');
        equal(p.status().textContent, 'Keine Vorschläge', 'announced');
        p.type('zzz'); p.flush();
        p.requests[1].answer({ error_text: 'Undefined server' }, 500);
        await tick();
        equal(p.input.getAttribute('aria-expanded'), 'false', 'a failed lookup shows nothing');
        p.type('zzzz'); p.flush();
        p.requests[2].fail();
        await tick();
        equal(p.options().length, 0, 'a network error shows nothing');
        p = page({ minQueryLength: -1 });
        equal(p.input.getAttribute('role'), null, 'a negative minQueryLength turns the suggestions off');
        equal(p.list(), null, 'no listbox');
    });

    await check('AC-09 parseSuggestions takes [word, count] pairs and plain words, without duplicates, up to the limit', () => {
        page();
        const parse = window.ezfindAutocomplete.parseSuggestions;
        equal(JSON.stringify(parse({ content: [['a', 2], 'b', ['a', 1], [3, 1], null, ['c', 1]] }, 2)),
            JSON.stringify([{ text: 'a', count: 2 }, { text: 'b', count: null }]), 'parsed');
        equal(parse({ content: 'x' }, 5).length, 0, 'not a list');
        equal(parse(null, 5).length, 0, 'no answer');
    });

    process.exit(failures ? 1 : 0);
})();
