/*
 * ezfind: the search pages and the administration's search box without inline code, so they work under a Content
 * Security Policy without 'unsafe-inline'. What the inline scripts and onclick attributes did is done here:
 *
 *   data-ezfind-toggle="ezfHelp"     a click shows or hides that block (the search tips) and remembers it in the
 *                                    cookie eZFind_<id> for a year
 *   data-ezfind-remember             a block shown or hidden as the cookie eZFind_<id> says when the page loads
 *                                    (hidden without one)
 *   data-ezfind-searchscope          the administration's search form (page_search.tpl): the search field's button,
 *                                    the search scope pane (open, close) and its location choices
 *
 * Plain JavaScript; works in every admin design. Set up once, however often this file is printed.
 */
( function()
{
    if ( window.eZFindSearch ) return;
    window.eZFindSearch = { 'version': 1 };

    function getCookie( name )
    {
        var cookieName = 'eZFind_' + name;
        var list = document.cookie.split( ';' );
        for ( var i = 0; i < list.length; i++ )
        {
            var cookie = list[i].split( '=' );
            if ( cookie[0].replace( /^\s+|\s+$/g, '' ) == cookieName )
            {
                return cookie[1];
            }
        }
        return 'none';
    }

    function setCookie( name, value )
    {
        var expires = new Date();
        expires.setTime( expires.getTime() + ( 365 * 24 * 60 * 60 * 1000 ) );
        document.cookie = 'eZFind_' + name + '=' + value + '; expires=' + expires + ';';
    }

    function setBlock( id, value )
    {
        var el = document.getElementById( id );
        if ( el != null )
        {
            // the hidden attribute, not an inline style (the block starts with hidden="hidden")
            el.hidden = value === 'none';
            if ( el.style.display ) el.style.display = '';
        }
    }

    document.addEventListener( 'click', function( e )
    {
        var el = e.target && e.target.closest ? e.target.closest( '[data-ezfind-toggle]' ) : null;
        if ( el )
        {
            var id = el.getAttribute( 'data-ezfind-toggle' ), block = document.getElementById( id );
            if ( !block ) return;
            e.preventDefault();
            var value = ( block.hidden || block.style.display == 'none' ) ? 'block' : 'none';
            setBlock( id, value );
            setCookie( id, value );
            return;
        }

        var form = e.target && e.target.closest ? e.target.closest( 'form[data-ezfind-searchscope]' ) : null;
        if ( !form ) return;
        var text = document.getElementById( 'searchtext' ), pane = document.getElementById( 'searchscope-pane' );
        if ( e.target.closest( '#searchbuttonfield' ) )
        {
            if ( text && text.value === text.getAttribute( 'title' ) ) return;
            var button = document.getElementById( 'searchbutton' );
            if ( button ) button.click();
        }
        else if ( e.target.closest( '#searchscope-pane-close' ) )
        {
            e.preventDefault();
            if ( pane ) pane.classList.remove( 'active' );
        }
        else if ( e.target.closest( '#searchscope' ) )
        {
            if ( pane ) pane.classList.add( 'active' );
        }
    } );

    document.addEventListener( 'change', function( e )
    {
        var radio = e.target;
        if ( !radio || radio.type !== 'radio' || radio.name !== 'SubTreeArray' || !radio.closest( 'form[data-ezfind-searchscope]' ) ) return;
        var text = document.getElementById( 'searchtext' );
        if ( !text ) return;
        text.setAttribute( 'value', radio.getAttribute( 'title' ) || '' );
        text.setAttribute( 'title', radio.getAttribute( 'title' ) || '' );
    } );

    function start()
    {
        var blocks = document.querySelectorAll( '[data-ezfind-remember][id]' );
        for ( var i = 0; i < blocks.length; i++ )
        {
            setBlock( blocks[i].id, getCookie( blocks[i].id ) );
        }
    }

    if ( document.readyState === 'loading' )
        document.addEventListener( 'DOMContentLoaded', start );
    else
        start();
} )();
