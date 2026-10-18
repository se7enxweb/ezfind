/*
 * ezfind: search suggestions for a search field, as an accessible combobox. Plain JavaScript, no library.
 *
 * A text field with an id and data-ezfind-autocomplete (JSON) gets the suggestions when the page is ready:
 *
 *   url             the server function, 'ezjscore/call/ezfind::autocomplete'|ezurl( 'no' )
 *   minQueryLength  characters typed before a lookup (ezfind.ini [AutoCompleteSettings] MinQueryLength);
 *                   a negative value turns the suggestions off
 *   resultLimit     suggestions asked for (ezfind.ini [AutoCompleteSettings] Limit)
 *   subtree         optional, a node id the suggestions are limited to
 *   classes         optional, a comma separated list of class ids or identifiers
 *   delay           optional, milliseconds of typing pause before a lookup (default 250)
 *   label           optional, the name of the suggestion list (default: the field's name)
 *   statusText      optional, read out when suggestions are shown, %count is replaced (default "%count suggestions")
 *   noResultsText   optional, read out when nothing matches (default "No suggestions")
 *
 * The lookup is GET <url>::<text>::<resultLimit>::<subtree>::<classes>?ContentType=json, answered by
 * eZFindServerCallFunctions::autocomplete() as { "error_text": "", "content": [ [ "word", count ], ... ] }.
 *
 * The field becomes a combobox (role, aria-expanded, aria-controls, aria-activedescendant) with a listbox of
 * options after it. Down and Up move through the suggestions (Alt+Down opens the list), Enter takes the highlighted
 * one into the field, Escape closes the list, a click on a suggestion takes it. Without a highlighted suggestion
 * Enter submits the search as before. A field gets the suggestions once, however often this file is printed.
 *
 * No inline code or styles: the look is in ezajax_autocomplete.css of the design (class names ezfind-ac-*).
 */
( function()
{
    'use strict';

    if ( window.ezfindAutocomplete ) return;

    var DEFAULT_DELAY = 250;

    // The suggestion URL for a text, following the server function's argument order
    function suggestionUrl( conf, query )
    {
        var url = conf.url + '::' + encodeURIComponent( query ) + '::' + ( parseInt( conf.resultLimit, 10 ) || 10 ) + '::';
        if ( conf.subtree !== undefined && conf.subtree !== null && conf.subtree !== '' )
            url += encodeURIComponent( String( conf.subtree ) );
        url += '::';
        if ( conf.classes !== undefined && conf.classes !== null && conf.classes !== '' )
            url += encodeURIComponent( String( conf.classes ) );
        return url + '?ContentType=json';
    }

    // The suggestions of an answer: [ { text, count } ], at most limit of them
    function parseSuggestions( response, limit )
    {
        var list = response && response.content, out = [], seen = {};
        if ( !Array.isArray( list ) ) return out;
        for ( var i = 0; i < list.length; i++ )
        {
            var item = list[i], text = null, count = null;
            if ( typeof item === 'string' ) text = item;
            else if ( item && typeof item[0] === 'string' ) { text = item[0]; count = item[1]; }
            if ( text === null || text === '' || seen[text] ) continue;
            seen[text] = true;
            out.push( { 'text': text, 'count': typeof count === 'number' ? count : null } );
            if ( limit > 0 && out.length >= limit ) break;
        }
        return out;
    }

    // Appends text to parent with every case-insensitive occurrence of query in a <mark>
    function appendHighlighted( parent, text, query )
    {
        var doc = parent.ownerDocument, lower = text.toLowerCase(), q = query.toLowerCase(), from = 0, at;
        var addText = function( value ) { if ( value !== '' ) { var s = doc.createElement( 'span' ); s.textContent = value; parent.appendChild( s ); } };
        if ( q !== '' )
        {
            while ( ( at = lower.indexOf( q, from ) ) !== -1 )
            {
                addText( text.substring( from, at ) );
                var mark = doc.createElement( 'mark' );
                mark.className = 'ezfind-ac-match';
                mark.textContent = text.substring( at, at + q.length );
                parent.appendChild( mark );
                from = at + q.length;
            }
        }
        addText( text.substring( from ) );
    }

    function labelOf( input )
    {
        if ( input.getAttribute( 'aria-label' ) ) return input.getAttribute( 'aria-label' );
        if ( input.getAttribute( 'title' ) ) return input.getAttribute( 'title' );
        var labels = input.ownerDocument.querySelectorAll( 'label[for="' + input.id + '"]' );
        if ( labels.length && labels[0].textContent.replace( /\s+/g, ' ' ).trim() !== '' )
            return labels[0].textContent.replace( /\s+/g, ' ' ).trim();
        return 'Search';
    }

    function attach( input, conf )
    {
        if ( !input || !input.id || input.getAttribute( 'data-ezfind-autocomplete-ready' ) ) return null;
        conf = conf || {};
        var minLength = parseInt( conf.minQueryLength, 10 );
        if ( isNaN( minLength ) ) minLength = 2;
        if ( minLength < 0 || !conf.url ) return null;
        input.setAttribute( 'data-ezfind-autocomplete-ready', '1' );

        var doc = input.ownerDocument,
            limit = parseInt( conf.resultLimit, 10 ) || 10,
            delay = conf.delay !== undefined ? Math.max( 0, parseInt( conf.delay, 10 ) || 0 ) : DEFAULT_DELAY,
            listId = input.id + '-ezfind-suggestions',
            items = [], active = -1, open = false, timer = null, seq = 0, controller = null, cache = {}, lastQuery = null;

        var list = doc.createElement( 'ul' );
        list.id = listId;
        list.className = 'ezfind-ac-list';
        list.setAttribute( 'role', 'listbox' );
        list.setAttribute( 'aria-label', conf.label || labelOf( input ) );
        list.hidden = true;

        var status = doc.createElement( 'span' );
        status.className = 'ezfind-ac-status';
        status.setAttribute( 'role', 'status' );

        var parent = input.parentNode;
        parent.classList.add( 'ezfind-ac-anchor' );
        if ( input.nextSibling ) parent.insertBefore( list, input.nextSibling );
        else parent.appendChild( list );
        parent.appendChild( status );

        input.setAttribute( 'role', 'combobox' );
        input.setAttribute( 'aria-autocomplete', 'list' );
        input.setAttribute( 'aria-expanded', 'false' );
        input.setAttribute( 'aria-controls', listId );
        input.setAttribute( 'autocomplete', 'off' );

        function setActive( index )
        {
            var options = list.children;
            for ( var i = 0; i < options.length; i++ )
            {
                var on = i === index;
                options[i].setAttribute( 'aria-selected', on ? 'true' : 'false' );
                options[i].classList.toggle( 'is-active', on );
            }
            active = index;
            if ( index >= 0 && options[index] )
            {
                input.setAttribute( 'aria-activedescendant', options[index].id );
                if ( typeof options[index].scrollIntoView === 'function' ) options[index].scrollIntoView( { 'block': 'nearest' } );
            }
            else
                input.removeAttribute( 'aria-activedescendant' );
        }

        function show()
        {
            if ( !items.length ) return;
            open = true;
            list.hidden = false;
            input.setAttribute( 'aria-expanded', 'true' );
        }

        function close()
        {
            open = false;
            list.hidden = true;
            input.setAttribute( 'aria-expanded', 'false' );
            setActive( -1 );
        }

        function render( suggestions, query )
        {
            items = suggestions;
            while ( list.firstChild ) list.removeChild( list.firstChild );
            for ( var i = 0; i < items.length; i++ )
            {
                var option = doc.createElement( 'li' );
                option.id = listId + '-' + i;
                option.className = 'ezfind-ac-option';
                option.setAttribute( 'role', 'option' );
                option.setAttribute( 'aria-selected', 'false' );
                option.setAttribute( 'data-index', String( i ) );
                appendHighlighted( option, items[i].text, query );
                list.appendChild( option );
            }
            active = -1;
            input.removeAttribute( 'aria-activedescendant' );
            if ( items.length )
            {
                show();
                status.textContent = ( conf.statusText || '%count suggestions' ).replace( '%count', String( items.length ) );
            }
            else
            {
                close();
                status.textContent = conf.noResultsText || 'No suggestions';
            }
        }

        function choose( index )
        {
            if ( index < 0 || index >= items.length ) return;
            input.value = items[index].text;
            lastQuery = input.value;
            close();
            status.textContent = '';
            if ( typeof window.CustomEvent === 'function' && typeof input.dispatchEvent === 'function' )
                input.dispatchEvent( new window.CustomEvent( 'ezfind:suggestion', { 'bubbles': true, 'detail': { 'text': input.value } } ) );
        }

        function lookup()
        {
            timer = null;
            var query = input.value;
            if ( query.replace( /^\s+|\s+$/g, '' ).length < minLength )
            {
                seq++;
                items = [];
                close();
                status.textContent = '';
                return;
            }
            if ( query === lastQuery && items.length ) { show(); return; }
            lastQuery = query;
            var mine = ++seq;
            if ( cache.hasOwnProperty( query ) ) { render( cache[query], query ); return; }
            if ( typeof window.fetch !== 'function' ) return;
            if ( controller && typeof controller.abort === 'function' ) controller.abort();
            controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
            var options = { 'method': 'GET', 'credentials': 'same-origin', 'headers': { 'Accept': 'application/json' } };
            if ( controller ) options.signal = controller.signal;
            window.fetch( suggestionUrl( conf, query ), options ).then( function( response )
            {
                if ( !response.ok ) throw new Error( 'HTTP ' + response.status );
                return response.json();
            } ).then( function( data )
            {
                var suggestions = parseSuggestions( data, limit );
                cache[query] = suggestions;
                // only the answer to the latest lookup, and only while the field still holds its text
                if ( mine !== seq || input.value !== query ) return;
                render( suggestions, query );
            } )[ 'catch' ]( function()
            {
                if ( mine === seq ) { items = []; close(); }
            } );
        }

        function schedule()
        {
            if ( timer !== null ) window.clearTimeout( timer );
            timer = window.setTimeout( lookup, delay );
        }

        input.addEventListener( 'input', schedule );

        input.addEventListener( 'keydown', function( e )
        {
            var key = e.key;
            if ( key === 'ArrowDown' || key === 'Down' )
            {
                e.preventDefault();
                if ( !open )
                {
                    if ( items.length && input.value === lastQuery ) { show(); if ( !e.altKey ) setActive( 0 ); }
                    else if ( timer === null ) lookup();
                    return;
                }
                if ( !e.altKey ) setActive( active + 1 >= items.length ? 0 : active + 1 );
            }
            else if ( key === 'ArrowUp' || key === 'Up' )
            {
                if ( !open ) return;
                e.preventDefault();
                setActive( active - 1 < 0 ? items.length - 1 : active - 1 );
            }
            else if ( key === 'Enter' )
            {
                if ( open && active >= 0 )
                {
                    e.preventDefault();
                    choose( active );
                }
                else if ( open ) close();
            }
            else if ( key === 'Escape' || key === 'Esc' )
            {
                if ( open )
                {
                    e.preventDefault();
                    close();
                }
            }
            else if ( key === 'Tab' )
            {
                if ( open && active >= 0 ) choose( active );
                else if ( open ) close();
            }
        } );

        input.addEventListener( 'blur', function()
        {
            if ( open ) close();
        } );

        // a press on the list keeps the focus in the field, so the field's blur does not close the list first
        list.addEventListener( 'mousedown', function( e ) { e.preventDefault(); } );
        list.addEventListener( 'click', function( e )
        {
            var option = e.target && e.target.closest ? e.target.closest( '[role="option"]' ) : null;
            if ( !option ) return;
            choose( parseInt( option.getAttribute( 'data-index' ), 10 ) );
            input.focus();
        } );

        return {
            'input': input, 'list': list, 'status': status,
            'lookup': lookup, 'close': close,
            'isOpen': function() { return open; },
            'active': function() { return active; }
        };
    }

    function setup( root )
    {
        var fields = ( root || document ).querySelectorAll( '[data-ezfind-autocomplete]' ), out = [];
        for ( var i = 0; i < fields.length; i++ )
        {
            if ( !fields[i].id ) continue;
            var conf;
            try { conf = JSON.parse( fields[i].getAttribute( 'data-ezfind-autocomplete' ) ); }
            catch ( e ) { continue; }
            var widget = attach( fields[i], conf );
            if ( widget ) out.push( widget );
        }
        return out;
    }

    window.ezfindAutocomplete = {
        'version': 2,
        'attach': attach,
        'setup': setup,
        'suggestionUrl': suggestionUrl,
        'parseSuggestions': parseSuggestions
    };

    if ( document.readyState === 'loading' )
        document.addEventListener( 'DOMContentLoaded', function() { setup(); } );
    else
        setup();
} )();
