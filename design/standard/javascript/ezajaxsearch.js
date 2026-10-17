var eZAJAXSearch = function()
{
    var ret = {};

    var yCallback = function(Y, result)
    {
        var successCallBack = function(id, o)
        {
            if (o.responseJSON !== undefined)
            {
                var response = o.responseJSON;

                if (response.content.SearchResult !== undefined)
                {
                    var itemCount = response.content.SearchResult.length;

                    var resultsTarget = Y.one(ret.cfg.searchresults);
                    resultsTarget.set('innerHTML', '');
                    resultsTarget.addClass('loading');

                    var spellCheck = response.content.SearchExtras.spellcheck;
                    // A spellcheck proposal was made, display it :
                    if ( spellCheck && spellCheck.collation )
                    {
                        const scTempNode = Y.Node.create( '<div>' + spellCheck.collation + '</div>' );
                        const scEscaped = scTempNode.get('innerHTML');

                        var scTemplate = ret.cfg.spellchecktemplate;
                        scTemplate = scTemplate.replace( /\{+spellcheck+\}/g, scEscaped );
                        var scDiv = Y.Node.create( scTemplate );
                        scDiv.on( 'click', handleClickFromSpellcheck );
                        resultsTarget.appendChild( scDiv );
                    }

                    var facets = response.content.SearchExtras.facets;
                    // Facets were returned, display them :
                    if ( facets && facets.length != 0 )
                    {
                        var facetMainList = ret.cfg.facetsmainlisttemplate;
                        for( var i = 0; i < facets.length; i++ )
                        {
                            var facet = facets[i];
                            // Name of the facet :
                            var facetName = facet['name'];
                            var facetInnerList = ret.cfg.facetsinnerlisttemplate;
                            facetInnerList = facetInnerList.replace( /\{+facet_name+\}/g, facetName );

                            if ( facet['list'].length > 0 )
                            {
                                for( var j = 0; j < facet['list'].length; j++ )
                                {
                                    var link = facet['list'][j]['url'];
                                    var value = facet['list'][j]['value'];
                                    var count = facet['list'][j]['count'];
                                    var facetElement = ret.cfg.facetselementtemplate;
                                    facetElement = facetElement.replace( /\{+link+\}/g, link );
                                    facetElement = facetElement.replace( /\{+value+\}/g, value );
                                    facetElement = facetElement.replace( /\{+count+\}/g, count );
                                    facetInnerList = facetInnerList.replace( /\{+facet_element+\}/g, facetElement + "{facet_element}" );
                                }
                                facetInnerList = facetInnerList.replace( /\{+facet_element+\}/g, '' );
                                facetMainList = facetMainList.replace( /\{+inner_facet_list+\}/g, facetInnerList + "{inner_facet_list}" );
                            }
                        }

                        // Only display the "Refine with facets" block if actual facets were returned.
                        if ( facetMainList != ret.cfg.facetsmainlisttemplate )
                        {
                            facetMainList = facetMainList.replace( /\{+inner_facet_list+\}/g, "" );
                            var facetsDiv = Y.Node.create( facetMainList );

                            resultsTarget.appendChild( facetsDiv );
                            var id = facetsDiv.get( 'id' );
                            //var width = facetsDiv.get( 'clientWidth' ) - 10; // removing horizontal padding in order to have the actual element's width
                            //var height = facetsDiv.get( 'clientHeight' ) - 10; // idem.

                            var myAnim = function ( Y )
                            {
                                var anim = new Y.Anim({
                                    node: '#' + id,
                                    easing: Y.Easing.backIn,
                                    duration: 0.5,

                                    from:
                                    {
                                        /*height: 0,
                                        width: 0*/
                                        opacity: 0
                                    },

                                    to:
                                    {
                                        /*width: width,
                                        height: height*/
                                        opacity: 1
                                    }
                                });
                                anim.run();
                            }
                            YUI().use( 'animation', 'anim', myAnim );

                        }
                    }

                    for(var i = 0; i < itemCount; i++)
                    {
                        var item = response.content.SearchResult[i];

                        var template = ret.cfg.resulttemplate;
                        template = template.replace(/\{+title+\}/g, item.name);
                        if ( item.published_date === undefined )
                        {
                            var date = new Date( item.published * 1000 );
                            var dateString = date.getHours() + ':' + date.getMinutes() + ':' + date.getSeconds() + ' ' + date.getFullYear() + '/' + date.getMonth() + '/' + date.getDay();
                            template = template.replace(/\{+date+\}/g, dateString);
                        }
                        else
                        {
                            template = template.replace(/\{+date+\}/g, item.published_date);
                        }
                        template = template.replace(/\{+class_name+\}/g, item.class_name);
                        template = template.replace(/\{+url_alias+\}/g, item.url_alias);
                        template = template.replace(/\{+object_id+\}/g, item.id);
                        template = template.replace(/\{+node_id+\}/g, item.node_id);

                        var itemContainer = Y.Node.create(template);

                        resultsTarget.removeClass('loading');
                        resultsTarget.appendChild(itemContainer);
                    }

                    if ( itemCount === 0 )
                    {
                        var itemContainer = Y.Node.create(ret.cfg.noresultstring);
                        resultsTarget.removeClass('loading');
                        resultsTarget.appendChild(itemContainer);
                    }
                }
            }
        }

        var getValueForSelector = function(sel)
        {
            var value, node = Y.one(sel);

            if ( node )
            {
                if ( node.get('nodeName').toLowerCase() === 'input'
                     && ( node.get('type') === 'radio' || node.get('type') === 'checkbox') )
                {
                    value = (Y.one(sel + ':checked') != null) ? Y.one(sel + ':checked').get('value') : null;
                }
                else if ( node.get('nodeName').toLowerCase() == 'select'
                          && node.hasAttribute('multiple') )
                {
                    value = [];
                    node.get('options').each(function( option )
                    {
                        if ( option.get('selected') )
                            value.push( option.get('value') );
                    });
                    value = value.join(',');
                }
                else
                {
                    value = node.get('value');
                }
            }

            return value;
        }

        var performSearch = function()
        {
            var searchString = getValueForSelector(ret.cfg.searchstring);
            var dateFormatType = ret.cfg.dateformattype !== undefined ? ret.cfg.dateformattype : 'shortdatetime';

            var value, data = 'SearchStr=' + searchString;
            data += '&SearchLimit=' + getValueForSelector('[name=SearchLimit]');

            if (value = getValueForSelector('[name=SearchOffset]'))
                data += '&SearchOffset=' + value;

            if (value = getValueForSelector('[name=SearchSectionID]'))
                data += '&SearchSectionID=' + value;

            if (value = getValueForSelector('[name=SearchDate]'))
                data += '&SearchDate=' +  value;

            if (value = getValueForSelector('[name=SearchContentClassAttributeID]'))
                data += '&SearchContentClassAttributeID=' + value;

            if (value = getValueForSelector('[name=SearchContentClassID]'))
                data += '&SearchContentClassID=' + value;

            if (value = getValueForSelector('[name=SearchContentClassIdentifier]'))
                data += '&SearchContentClassIdentifier=' + value;

            if (value = getValueForSelector('[name=SearchSubTreeArray]'))
                data += '&SearchSubTreeArray=' + value;

            if (value = getValueForSelector('[name=SearchTimestamp]'))
                data += '&SearchTimestamp=' + value;

            data += '&EncodingFormatDate=' + dateFormatType;

            if ( ret.cfg.customSearchAttributes !== undefined )
            {
                for ( var i = 0, l = ret.cfg.customSearchAttributes.length; i < l; i++ )
                {
                    data += '&' + Y.one( ret.cfg.customSearchAttributes[i] ).get('name') + '=' + Y.one( ret.cfg.customSearchAttributes[i] ).get('value');
                }
            }

            var backendUri = ret.cfg.backendUri ? ret.cfg.backendUri : 'ezjsc::search' ;

            if(searchString !== '')
            {
                Y.io.ez(backendUri, {on: {success: successCallBack}, method: 'POST', data: data });
            }
        }

        var handleClick = function(e)
        {
            performSearch();
            e.preventDefault();
        }

        var handleClickFromSpellcheck = function(e)
        {
            Y.one(ret.cfg.searchstring).set( 'value', Y.one(ret.cfg.spellcheck).get('innerHTML') );
            handleClick( e );
        }

        var handleKeyPress = function(e)
        {
            if (e.keyCode == 13)
            {
                performSearch();
                e.preventDefault();
            }
        }

        Y.one(ret.cfg.searchbutton).on('click', handleClick);
        Y.one(ret.cfg.searchstring).on('keypress', handleKeyPress);
    }
    ret.cfg = {};

    ret.init = function()
    {
        var ins = YUI(YUI3_config).use('node', 'event', 'io-ez', yCallback);
    }

    return ret;
}();

/*
 * Templates without inline code: an element with data-ezflow-ajaxsearch (the same markup ezflow's ezajaxsearch.js
 * reads) carries the settings above as data attributes: data-search-string, data-search-button, data-search-results,
 * data-date-format, data-result-template, data-noresult-string, data-spellcheck, data-spellcheck-template,
 * data-facets-main-template, data-facets-inner-template, data-facets-element-template, data-backend-uri and
 * data-custom-search-attributes (a JSON list of selectors). The search starts from them when the page is ready, the
 * way the inline "eZAJAXSearch.cfg = { ... }; eZAJAXSearch.init();" did. It needs YUI 3 (ezjsc::yui3); without it
 * nothing happens. Set up once, however often this file is printed.
 */
( function()
{
    if ( eZAJAXSearch.dataSetup ) return;
    eZAJAXSearch.dataSetup = true;

    var keys = { 'searchstring': 'data-search-string', 'searchbutton': 'data-search-button',
                 'searchresults': 'data-search-results', 'dateformattype': 'data-date-format',
                 'resulttemplate': 'data-result-template', 'noresultstring': 'data-noresult-string',
                 'spellcheck': 'data-spellcheck', 'spellchecktemplate': 'data-spellcheck-template',
                 'facetsmainlisttemplate': 'data-facets-main-template',
                 'facetsinnerlisttemplate': 'data-facets-inner-template',
                 'facetselementtemplate': 'data-facets-element-template', 'backendUri': 'data-backend-uri' };

    var start = function()
    {
        if ( !window.YUI ) return;
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
