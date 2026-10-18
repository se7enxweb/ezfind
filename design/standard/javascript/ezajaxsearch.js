/*
 * ezfind: the AJAX search of the ezflow search block. Plain JavaScript, no library.
 *
 * eZAJAXSearch.cfg holds the settings and eZAJAXSearch.init() starts the search on them, as before:
 *   searchstring, searchbutton, searchresults   selectors of the text field, the button and the result container
 *   resulttemplate, noresultstring              HTML of a result ({title}, {date}, {class_name}, {url_alias},
 *                                               {object_id}, {node_id}) and of "no results"
 *   spellcheck, spellchecktemplate              the "did you mean" link and its HTML ({spellcheck})
 *   facetsmainlisttemplate, facetsinnerlisttemplate, facetselementtemplate
 *                                               HTML of the facets ({inner_facet_list}, {facet_name},
 *                                               {facet_element}, {link}, {value}, {count})
 *   dateformattype                              the date format of the results (default shortdatetime)
 *   backendUri                                  the server function (default ezjsc::search)
 *   callUrl                                     ezjscore's call URL (default /ezjscore/call)
 *   customSearchAttributes                      selectors of further fields posted with the search
 *
 * Templates without inline code: an element with data-ezflow-ajaxsearch carries the same settings as data attributes
 * (data-search-string, data-search-button, data-search-results, data-date-format, data-result-template,
 * data-noresult-string, data-spellcheck, data-spellcheck-template, data-facets-main-template,
 * data-facets-inner-template, data-facets-element-template, data-backend-uri, data-call-url and
 * data-custom-search-attributes, a JSON list of selectors); the search starts from them when the page is ready.
 *
 * The values the server returns are escaped before they go into the HTML templates. Set up once, however often this
 * file is printed.
 */
var eZAJAXSearch = window.eZAJAXSearch || function()
{
    'use strict';

    var ret = { 'cfg': {} };

    function escapeHtml( value )
    {
        return String( value === undefined || value === null ? '' : value )
            .replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
    }

    function fill( template, values )
    {
        return String( template || '' ).replace( /\{+([a-z_]+)\}+/g, function( all, name )
        {
            return values.hasOwnProperty( name ) ? values[name] : all;
        } );
    }

    function nodesOf( html )
    {
        var box = document.createElement( 'div' );
        box.innerHTML = html;
        return Array.prototype.slice.call( box.childNodes );
    }

    function valueOf( selector )
    {
        var node = document.querySelector( selector );
        if ( !node ) return null;
        var tag = node.tagName.toLowerCase();
        if ( tag === 'input' && ( node.type === 'radio' || node.type === 'checkbox' ) )
        {
            var checked = document.querySelector( selector + ':checked' );
            return checked ? checked.value : null;
        }
        if ( tag === 'select' && node.multiple )
        {
            var values = [];
            for ( var i = 0; i < node.options.length; i++ )
                if ( node.options[i].selected ) values.push( node.options[i].value );
            return values.join( ',' );
        }
        return node.value;
    }

    function token()
    {
        var node = document.getElementById( 'ezxform_token_js' );
        return node ? node.getAttribute( 'title' ) || '' : '';
    }

    function formatDate( item )
    {
        if ( item.published_date !== undefined ) return item.published_date;
        var date = new Date( item.published * 1000 );
        return date.toLocaleString();
    }

    function showResults( content, settings )
    {
        var cfg = settings || ret.cfg, target = document.querySelector( cfg.searchresults );
        if ( !target || !content || content.SearchResult === undefined ) return;
        while ( target.firstChild ) target.removeChild( target.firstChild );

        var extras = content.SearchExtras || {}, spellCheck = extras.spellcheck;
        if ( spellCheck && spellCheck.collation && cfg.spellchecktemplate )
        {
            nodesOf( fill( cfg.spellchecktemplate, { 'spellcheck': escapeHtml( spellCheck.collation ) } ) ).forEach( function( node )
            {
                if ( node.addEventListener ) node.addEventListener( 'click', function( e )
                {
                    var field = document.querySelector( cfg.searchstring );
                    if ( field ) field.value = spellCheck.collation;
                    e.preventDefault();
                    ret.search( cfg );
                } );
                target.appendChild( node );
            } );
        }

        var facets = extras.facets;
        if ( facets && facets.length && cfg.facetsmainlisttemplate )
        {
            var inner = '';
            for ( var i = 0; i < facets.length; i++ )
            {
                var facet = facets[i], list = facet.list || [];
                if ( !list.length ) continue;
                var elements = '';
                for ( var j = 0; j < list.length; j++ )
                {
                    elements += fill( cfg.facetselementtemplate, { 'link': escapeHtml( list[j].url ),
                        'value': escapeHtml( list[j].value ), 'count': escapeHtml( list[j].count ) } );
                }
                inner += fill( cfg.facetsinnerlisttemplate, { 'facet_name': escapeHtml( facet.name ), 'facet_element': elements } );
            }
            // the "Refine with facets" block only when there are facets
            if ( inner !== '' )
            {
                nodesOf( fill( cfg.facetsmainlisttemplate, { 'inner_facet_list': inner } ) ).forEach( function( node )
                {
                    target.appendChild( node );
                    // faded in by the stylesheet (ezfind.css, .ajax-search-facets.is-shown)
                    if ( node.classList ) window.setTimeout( function() { node.classList.add( 'is-shown' ); }, 0 );
                } );
            }
        }

        var results = content.SearchResult;
        for ( var k = 0; k < results.length; k++ )
        {
            var item = results[k];
            nodesOf( fill( cfg.resulttemplate, { 'title': escapeHtml( item.name ), 'date': escapeHtml( formatDate( item ) ),
                'class_name': escapeHtml( item.class_name ), 'url_alias': escapeHtml( item.url_alias ),
                'object_id': escapeHtml( item.id ), 'node_id': escapeHtml( item.node_id ) } ) ).forEach( function( node )
            {
                target.appendChild( node );
            } );
        }
        if ( !results.length && cfg.noresultstring )
        {
            nodesOf( cfg.noresultstring ).forEach( function( node ) { target.appendChild( node ); } );
        }
        target.classList.remove( 'loading' );
    }

    ret.search = function( settings )
    {
        var cfg = settings || ret.cfg, searchString = valueOf( cfg.searchstring );
        if ( !searchString || typeof window.fetch !== 'function' ) return;

        var data = new window.URLSearchParams(), value;
        data.append( 'SearchStr', searchString );
        data.append( 'SearchLimit', valueOf( '[name=SearchLimit]' ) || '' );
        var optional = [ 'SearchOffset', 'SearchSectionID', 'SearchDate', 'SearchContentClassAttributeID',
                         'SearchContentClassID', 'SearchContentClassIdentifier', 'SearchSubTreeArray', 'SearchTimestamp' ];
        for ( var i = 0; i < optional.length; i++ )
        {
            if ( ( value = valueOf( '[name=' + optional[i] + ']' ) ) ) data.append( optional[i], value );
        }
        data.append( 'EncodingFormatDate', cfg.dateformattype || 'shortdatetime' );
        var custom = cfg.customSearchAttributes || [];
        for ( var j = 0; j < custom.length; j++ )
        {
            var field = document.querySelector( custom[j] );
            if ( field ) data.append( field.name, field.value );
        }
        data.append( 'ezjscServer_function_arguments', cfg.backendUri || 'ezjsc::search' );
        data.append( 'ezxform_token', token() );

        var target = document.querySelector( cfg.searchresults );
        if ( target ) target.classList.add( 'loading' );

        window.fetch( ( cfg.callUrl || '/ezjscore/call' ).replace( /\/$/, '' ) + '/', {
            'method': 'POST', 'credentials': 'same-origin', 'body': data,
            'headers': { 'Accept': 'application/json' }
        } ).then( function( response ) { return response.json(); } ).then( function( json )
        {
            showResults( json && json.content, cfg );
        } )[ 'catch' ]( function()
        {
            if ( target ) target.classList.remove( 'loading' );
        } );
    };

    ret.init = function()
    {
        var cfg = ret.cfg, button = document.querySelector( cfg.searchbutton ), field = document.querySelector( cfg.searchstring );
        if ( button ) button.addEventListener( 'click', function( e ) { e.preventDefault(); ret.search( cfg ); } );
        if ( field ) field.addEventListener( 'keydown', function( e )
        {
            // Enter searches, unless a search suggestion is being chosen
            if ( e.key === 'Enter' && !e.defaultPrevented )
            {
                e.preventDefault();
                ret.search( cfg );
            }
        } );
    };

    ret.showResults = showResults;
    ret.escapeHtml = escapeHtml;

    return ret;
}();
window.eZAJAXSearch = eZAJAXSearch;

( function()
{
    'use strict';

    if ( eZAJAXSearch.dataSetup ) return;
    eZAJAXSearch.dataSetup = true;

    var keys = { 'searchstring': 'data-search-string', 'searchbutton': 'data-search-button',
                 'searchresults': 'data-search-results', 'dateformattype': 'data-date-format',
                 'resulttemplate': 'data-result-template', 'noresultstring': 'data-noresult-string',
                 'spellcheck': 'data-spellcheck', 'spellchecktemplate': 'data-spellcheck-template',
                 'facetsmainlisttemplate': 'data-facets-main-template',
                 'facetsinnerlisttemplate': 'data-facets-inner-template',
                 'facetselementtemplate': 'data-facets-element-template', 'backendUri': 'data-backend-uri',
                 'callUrl': 'data-call-url' };

    var start = function()
    {
        var boxes = document.querySelectorAll( '[data-ezflow-ajaxsearch]' );
        for ( var i = 0; i < boxes.length; i++ )
        {
            var box = boxes[i];
            if ( box.getAttribute( 'data-ezflow-ajaxsearch-ready' ) ) continue;
            box.setAttribute( 'data-ezflow-ajaxsearch-ready', '1' );
            var cfg = {};
            for ( var key in keys )
            {
                if ( box.hasAttribute( keys[key] ) ) cfg[key] = box.getAttribute( keys[key] );
            }
            if ( box.hasAttribute( 'data-custom-search-attributes' ) )
            {
                try
                {
                    cfg.customSearchAttributes = JSON.parse( box.getAttribute( 'data-custom-search-attributes' ) );
                }
                catch ( e ) {}
            }
            eZAJAXSearch.cfg = cfg;
            eZAJAXSearch.init();
        }
    };

    if ( document.readyState === 'loading' )
        document.addEventListener( 'DOMContentLoaded', start );
    else
        start();
} )();
