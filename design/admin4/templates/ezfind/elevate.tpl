{* The Elevate overview (ezfind/elevate) in the admin4 design.

   The same view as design/standard/templates/ezfind/elevate.tpl, with the same form fields and buttons (the view,
   modules/ezfind/elevate.php, reads them by name), built from the admin4 components: notices for the feedback, one
   card each for elevating an object, finding elevated objects and synchronising with Solr, and the existing
   configurations as a table. No inline CSS. Values from the request are washed, except the search query: the view
   passes it, and stores it, already encoded with htmlspecialchars() (as the standard template expects), so washing
   it again would show &amp; and break the filter's round trip. A search query is put into a link through ezurl,
   which encodes it. *}
{if is_set( $back_from_browse )|not}
    {def $back_from_browse = false()}
{/if}
{if is_set( $elevateSearchQuery )|not}
    {def $elevateSearchQuery = ''}
{/if}
{def $preview = ezpreference( 'ezfind_elevate_preview_configurations' )}

<div class="context-block a4-page">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Elevation'|i18n( 'extension/ezfind/elevate' )}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

<p class="a4-intro">{'"Elevating an object" for a given search query means that this object will be returned among the very first search results when a search is triggered using this search query.'|i18n( 'extension/ezfind/elevate' )}</p>

{if or( is_set( $feedback.missing_searchquery ), is_set( $feedback.missing_object ) )}
<div class="a4-notice is-warn" role="alert">
    {if is_set( $feedback.missing_searchquery )}<p>{'Please enter a search query.'|i18n( 'extension/ezfind/elevate' )}</p>{/if}
    {if is_set( $feedback.missing_object )}<p>{'Please choose a valid object.'|i18n( 'extension/ezfind/elevate' )}</p>{/if}
</div>
{/if}
{if is_set( $feedback.missing_language )}
<div class="a4-notice is-warn" role="alert"><p>{'Missing language information.'|i18n( 'extension/ezfind/elevate' )}</p></div>
{/if}
{if is_set( $feedback.creation_ok )}
<div class="a4-notice is-ok" role="status"><p>{'Successful creation of the following Elevate configuration'|i18n( 'extension/ezfind/elevate' )}: <a href={concat( '/ezfind/elevation_detail/', $feedback.creation_ok.contentobject_id )|ezurl}>{'Details'|i18n( 'extension/ezfind/elevate' )}</a></p></div>
{/if}
{if is_set( $feedback.creation_error )}
<div class="a4-notice is-bad" role="alert"><p>{'The Elevate configuration could not be stored.'|i18n( 'extension/ezfind/elevate' )}</p></div>
{/if}
{if is_set( $feedback.synchronisation_ok )}
<div class="a4-notice is-ok" role="status"><p>{"Successful synchronization of the local Elevate configuration with Solr's."|i18n( 'extension/ezfind/elevate' )}</p></div>
{/if}
{if is_set( $feedback.synchronisation_fail )}
<div class="a4-notice is-bad" role="alert"><p>{$feedback.synchronisation_fail_message|wash}</p></div>
{/if}

{* one form per card: Enter in a field submits the first button of its own card *}
<form name="ezfindelevateform" method="post" action={'/ezfind/elevate/'|ezurl}>
<section class="a4-card{if $back_from_browse} is-attention{/if}" aria-labelledby="ezfind-elevate-title">
    <div class="a4-card-head">
        <h2 class="a4-card-title a4-h2" id="ezfind-elevate-title">{'Elevate an object'|i18n( 'extension/ezfind/elevate' )}</h2>
    </div>
    <div class="a4-card-body">
    {if $back_from_browse|not}
        <div class="a4-filterbar">
            <div class="a4-field">
                <label for="ezfind-elevate-searchquery">{'Search query'|i18n( 'extension/ezfind/elevate' )}</label>
                <input type="text" class="a4-input" id="ezfind-elevate-searchquery" name="ezfind-elevate-searchquery" value="" title="{'Search query to elevate the object for.'|i18n( 'extension/ezfind/elevate' )|wash}" />
            </div>
            <div class="a4-field">
                <div class="a4-actions">
                    <button type="submit" class="a4-btn a4-btn-primary" name="ezfind-elevate-browseforobject" value="1" title="{'Browse for the object to associate elevation to.'|i18n( 'extension/ezfind/elevate' )|wash}">{'Elevate object'|i18n( 'extension/ezfind/elevate' )}</button>
                </div>
            </div>
        </div>
    {else}
        <p>{'Object'|i18n( 'extension/ezfind/elevate' )}: <a href={$elevatedObject.main_node.url_alias|ezurl}>{$elevatedObject.name|wash}</a></p>
        <div class="a4-filterbar">
            <div class="a4-field">
                <label for="ezfind-elevate-searchquery">{'Search query'|i18n( 'extension/ezfind/elevate' )}</label>
                <input type="text" class="a4-input" id="ezfind-elevate-searchquery" name="ezfind-elevate-searchquery" value="{$elevateSearchQuery|wash}" title="{'Search query to elevate the object for.'|i18n( 'extension/ezfind/elevate' )|wash}" />
            </div>
            <div class="a4-field">
                <label for="ezfind-elevate-language">{'Language'|i18n( 'extension/ezfind/elevate' )}</label>
                <select class="a4-input" id="ezfind-elevate-language" name="ezfind-elevate-language">
                    <option value="{$language_wildcard|wash}">{'All'|i18n( 'extension/ezfind/elevate' )}</option>
                    {foreach $elevatedObject.languages as $lang}
                    <option value="{$lang.locale|wash}">{$lang.name|wash}</option>
                    {/foreach}
                </select>
            </div>
            <div class="a4-field">
                <input type="hidden" name="elevateObjectID" value="{$elevatedObject.id|wash}" />
                <div class="a4-actions">
                    <button type="submit" class="a4-btn a4-btn-primary" name="ezfind-elevate-do" value="1" title="{'Store elevation'|i18n( 'extension/ezfind/elevate' )|wash}">{'Elevate'|i18n( 'extension/ezfind/elevate' )}</button>
                    <button type="submit" class="a4-btn" name="ezfind-elevate-cancel" value="1" title="{'Cancel elevation'|i18n( 'extension/ezfind/elevate' )|wash}">{'Cancel'|i18n( 'extension/ezfind/elevate' )}</button>
                </div>
            </div>
        </div>
    {/if}
    </div>
</section>
</form>

<form name="ezfindsearchelevateform" method="post" action={'/ezfind/elevate/'|ezurl}>
<section class="a4-card" aria-labelledby="ezfind-search-title">
    <div class="a4-card-head">
        <h2 class="a4-card-title a4-h2" id="ezfind-search-title">{'Search for elevated objects'|i18n( 'extension/ezfind/elevate' )}</h2>
    </div>
    <div class="a4-card-body">
        <h3 class="a4-h3">{'By search query'|i18n( 'extension/ezfind/elevate' )}</h3>
        <div class="a4-filterbar">
            <div class="a4-field">
                <label for="ezfind-searchelevateconfigurations-searchquery">{'Search query'|i18n( 'extension/ezfind/elevate' )}</label>
                <input type="text" class="a4-input" id="ezfind-searchelevateconfigurations-searchquery" name="ezfind-searchelevateconfigurations-searchquery" value="{if is_set( $view_parameters.search_query )}{$view_parameters.search_query}{/if}" />
            </div>
            <div class="a4-field">
                <label for="ezfind-searchelevateconfigurations-language">{'Language'|i18n( 'extension/ezfind/elevate' )}</label>
                <select class="a4-input" id="ezfind-searchelevateconfigurations-language" name="ezfind-searchelevateconfigurations-language" title="{'Select a translation to narrow down the search.'|i18n( 'extension/ezfind/elevate' )|wash}">
                    <option value="{$language_wildcard|wash}"{if is_set( $view_parameters.language )|not} selected="selected"{/if}>{'All'|i18n( 'extension/ezfind/elevate' )}</option>
                    {foreach $available_translations as $translation}
                    <option value="{$translation.locale|wash}"{if and( is_set( $view_parameters.language ), eq( $view_parameters.language, $translation.locale ) )} selected="selected"{/if}>{$translation.name|wash}</option>
                    {/foreach}
                </select>
            </div>
            <div class="a4-field">
                <label class="a4-check"><input type="checkbox" id="ezfind-searchelevateconfigurations-fuzzy" name="ezfind-searchelevateconfigurations-fuzzy"{if is_set( $view_parameters.fuzzy_filter )} checked="checked"{/if} /><span>{'Fuzzy match'|i18n( 'extension/ezfind/elevate' )}</span></label>
            </div>
            <div class="a4-field">
                <div class="a4-actions">
                    <button type="submit" class="a4-btn" name="ezfind-searchelevateconfigurations-do" value="1" title="{'Find elevate configurations matching the search query entered.'|i18n( 'extension/ezfind/elevate' )|wash}"><span class="a4-icon a4-bi-search" aria-hidden="true"></span>{'Filter'|i18n( 'extension/ezfind/elevate' )}</button>
                </div>
            </div>
        </div>
        <h3 class="a4-h3">{'By object'|i18n( 'extension/ezfind/elevate' )}</h3>
        <div class="a4-actions">
            <button type="submit" class="a4-btn" name="ezfind-searchelevateconfigurations-browse" value="1" title="{'Choose an object to see its elevate configurations.'|i18n( 'extension/ezfind/elevate' )|wash}">{'Browse'|i18n( 'extension/ezfind/elevate' )}</button>
        </div>
    </div>
</section>
</form>

<form name="ezfindsynchroniseform" method="post" action={'/ezfind/elevate/'|ezurl}>
<section class="a4-card" aria-labelledby="ezfind-sync-title">
    <div class="a4-card-head">
        <h2 class="a4-card-title a4-h2" id="ezfind-sync-title">{'Synchronise Elevate configuration with Solr'|i18n( 'extension/ezfind/elevate' )}</h2>
    </div>
    <div class="a4-card-body">
        <div class="a4-actions">
            <button type="submit" class="a4-btn" name="ezfind-elevate-synchronise" value="1" title="{'Synchronise the Elevate configuration with Solr.'|i18n( 'extension/ezfind/elevate' )|wash}"><span class="a4-icon a4-bi-arrow-clockwise" aria-hidden="true"></span>{'Synchronise'|i18n( 'extension/ezfind/elevate' )}</button>
        </div>
    </div>
</section>
</form>

{* Existing configurations: shown while the preference is on (it also holds the page size: 1, 2, 3 for 10, 25, 50)
   or while a search query filters them *}
{def $limit = min( $preview, 3 )|choose( 10, 10, 25, 50 )
     $params = hash( 'offset', $view_parameters.offset, 'limit', $limit )
     $paramsForCount = hash( 'countOnly', true() )}
{if is_set( $view_parameters.search_query )}
    {def $searchQueryHash = hash( 'searchQuery', $view_parameters.search_query )}
    {if is_set( $view_parameters.fuzzy_filter )}
        {set $searchQueryHash = $searchQueryHash|merge( hash( 'fuzzy', true() ) )}
    {/if}
    {set $params = $params|merge( hash( 'searchQuery', $searchQueryHash ) )
         $paramsForCount = $paramsForCount|merge( hash( 'searchQuery', $searchQueryHash ) )}
{/if}
{if is_set( $view_parameters.language )}
    {set $params = $params|merge( hash( 'languageCode', $view_parameters.language ) )
         $paramsForCount = $paramsForCount|merge( hash( 'languageCode', $view_parameters.language ) )}
{/if}

<section class="a4-section" aria-labelledby="ezfind-configurations-title">
<div class="a4-section-head">
    <h2 class="a4-h2" id="ezfind-configurations-title">
    {if and( is_set( $view_parameters.search_query ), ne( $view_parameters.search_query, '' ) )}
        {'Objects elevated by "%search_query"'|i18n( 'extension/ezfind/elevate', '', hash( '%search_query', $view_parameters.search_query ) )}
        {if is_set( $view_parameters.fuzzy_filter )}({'fuzzy match'|i18n( 'extension/ezfind/elevate' )}){/if}
    {else}
        {'Existing configurations'|i18n( 'extension/ezfind/elevate' )}
    {/if}
    </h2>
    {if is_set( $view_parameters.search_query )|not}
    {* a toggle button with its state: a POST form (ezformtoken adds its token) that sets the preference and
       returns to this page, like the width control of the left menu *}
    <form class="a4-actions" method="post" action={'/user/preferences/set'|ezurl}>
        <input type="hidden" name="Key" value="ezfind_elevate_preview_configurations" />
        <input type="hidden" name="RedirectURIAfterSet" value="ezfind/elevate" />
        {if $preview}
        <button type="submit" class="a4-btn a4-btn-small" name="Value" value="0" aria-pressed="true" title="{'Hide preview of existing elevate configurations.'|i18n( 'extension/ezfind/elevate' )|wash}">{'Preview existing configurations'|i18n( 'extension/ezfind/elevate' )}</button>
        {else}
        <button type="submit" class="a4-btn a4-btn-small" name="Value" value="1" aria-pressed="false" title="{'Show preview of existing elevate configurations.'|i18n( 'extension/ezfind/elevate' )|wash}">{'Preview existing configurations'|i18n( 'extension/ezfind/elevate' )}</button>
        {/if}
    </form>
    {/if}
</div>

{if or( $preview, is_set( $view_parameters.search_query ) )}
    {def $configurations = fetch( 'ezfind', 'elevateConfiguration', $params )
         $configurations_count = fetch( 'ezfind', 'elevateConfiguration', $paramsForCount )}

    {if eq( $configurations_count, 0 )}
<p class="a4-empty">{'No existing Elevate configuration.'|i18n( 'extension/ezfind/elevate' )}</p>
    {elseif is_set( $view_parameters.search_query )}
<div class="a4-table-wrap">
<table class="a4-table">
    <thead>
        <tr>
            <th scope="col">{'Content object'|i18n( 'extension/ezfind/elevate' )}</th>
            <th scope="col"><span class="a4-sr-only">{'Actions'|i18n( 'extension/ezfind/elevate' )}</span></th>
        </tr>
    </thead>
    <tbody>
    {foreach $configurations as $object}
        {def $detail_url = concat( '/ezfind/elevation_detail/', $object.id, '/(search_query)/', $view_parameters.search_query )}
        {if is_set( $view_parameters.fuzzy_filter )}{set $detail_url = concat( $detail_url, '/(fuzzy_filter)/', $view_parameters.fuzzy_filter )}{/if}
        <tr>
            <td><a href={$object.main_node.url_alias|ezurl}>{$object.name|wash}</a></td>
            <td class="a4-end a4-nowrap">
                <a class="a4-icon-btn" href={$detail_url|ezurl} title="{'See elevate configuration details for \'%objectName\''|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $object.name ) )|wash}"><span class="a4-icon a4-bi-pencil" aria-hidden="true"></span><span class="a4-sr-only">{'See elevate configuration details for \'%objectName\''|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $object.name ) )|wash}</span></a>
                {if is_set( $view_parameters.fuzzy_filter )|not}
                <a class="a4-icon-btn" href={concat( '/ezfind/remove_elevation/', $object.id, '/', $view_parameters.search_query )|ezurl} title="{'Remove elevation by \'%searchQuery\' for \'%objectName\'.'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $object.name|wash, '%searchQuery', $view_parameters.search_query ) )}"><span class="a4-icon a4-bi-trash" aria-hidden="true"></span><span class="a4-sr-only">{'Remove elevation by \'%searchQuery\' for \'%objectName\'.'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $object.name|wash, '%searchQuery', $view_parameters.search_query ) )}</span></a>
                {/if}
            </td>
        </tr>
        {undef $detail_url}
    {/foreach}
    </tbody>
</table>
</div>
    {else}
<div class="a4-table-wrap">
<table class="a4-table">
    <thead>
        <tr>
            <th scope="col">{'Search query'|i18n( 'extension/ezfind/elevate' )}</th>
            <th scope="col">{'Content object'|i18n( 'extension/ezfind/elevate' )}</th>
            <th scope="col">{'Language'|i18n( 'extension/ezfind/elevate' )}</th>
            <th scope="col"><span class="a4-sr-only">{'Actions'|i18n( 'extension/ezfind/elevate' )}</span></th>
        </tr>
    </thead>
    <tbody>
    {def $tmp_obj = false()
         $obj_name = ''}
    {foreach $configurations as $conf}
        {set $tmp_obj = fetch( 'content', 'object', hash( 'object_id', $conf.contentobject_id ) )
             $obj_name = $conf.contentobject_id}
        {if $tmp_obj}{set $obj_name = $tmp_obj.name}{/if}
        <tr>
            <td><a href={concat( $baseurl, '/(search_query)/', $conf.search_query )|ezurl} title="{'See all objects elevated by \'%searchQuery\''|i18n( 'extension/ezfind/elevate', '', hash( '%searchQuery', $conf.search_query ) )}">{$conf.search_query}</a></td>
            <td>{if $tmp_obj}<a href={$tmp_obj.main_node.url_alias|ezurl}>{$tmp_obj.name|wash}</a>{else}<span class="a4-muted">{$conf.contentobject_id|wash}</span>{/if}</td>
            <td>{if eq( $conf.language_code, $language_wildcard )}<em>{'All'|i18n( 'extension/ezfind/elevate' )}</em>{else}{$conf.language_code|wash}{/if}</td>
            <td class="a4-end a4-nowrap">
                {if $tmp_obj}
                <a class="a4-icon-btn" href={concat( '/ezfind/elevation_detail/', $tmp_obj.id )|ezurl} title="{'See elevate configuration details for \'%objectName\''|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $tmp_obj.name ) )|wash}"><span class="a4-icon a4-bi-pencil" aria-hidden="true"></span><span class="a4-sr-only">{'See elevate configuration details for \'%objectName\''|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $tmp_obj.name ) )|wash}</span></a>
                {/if}
                <a class="a4-icon-btn" href={concat( '/ezfind/remove_elevation/', $conf.contentobject_id, '/', $conf.search_query, '/', $conf.language_code )|ezurl} title="{'Remove elevation by \'%searchQuery\' for \'%objectName\'.'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $obj_name|wash, '%searchQuery', $conf.search_query ) )}"><span class="a4-icon a4-bi-trash" aria-hidden="true"></span><span class="a4-sr-only">{'Remove elevation by \'%searchQuery\' for \'%objectName\'.'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $obj_name|wash, '%searchQuery', $conf.search_query ) )}</span></a>
            </td>
        </tr>
    {/foreach}
    {undef $tmp_obj $obj_name}
    </tbody>
</table>
</div>
    {/if}

    {if gt( $configurations_count, 0 )}
<div class="a4-listfoot">
    <p class="a4-sizes">
        <span>{'Per page'|i18n( 'extension/ezfind/elevate' )}:</span>
        {foreach array( hash( 'pref', 1, 'size', 10 ), hash( 'pref', 2, 'size', 25 ), hash( 'pref', 3, 'size', 50 ) ) as $size}
            {if eq( $limit, $size.size )}
        <span class="current" aria-current="true">{$size.size}</span>
            {else}
        <a href={concat( '/user/preferences/set/ezfind_elevate_preview_configurations/', $size.pref )|ezurl} title="{'Show %count items per page.'|i18n( 'extension/ezfind/elevate', '', hash( '%count', $size.size ) )|wash}">{$size.size}</a>
            {/if}
        {/foreach}
    </p>
    <div class="a4-pager">
    {include name=navigator
             uri='design:navigator/google.tpl'
             page_uri='/ezfind/elevate'
             item_count=$configurations_count
             view_parameters=$view_parameters
             item_limit=$limit}
    </div>
</div>
    {/if}
    {undef $configurations $configurations_count}
{/if}
</section>

</div></div></div>
</div>
