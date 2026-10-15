{* The Elevate configurations of one object (ezfind/elevation_detail) in the admin4 design.

   The same view as design/standard/templates/ezfind/elevation_detail.tpl, with the same form fields and buttons,
   built from the admin4 components: a card to elevate the object for another search query, a filter bar and the
   object's configurations as a table. No inline CSS; values from the request are washed, except the search query,
   which the view passes and stores already encoded with htmlspecialchars(). *}
{def $object_name = ''}
{if is_set( $elevatedObject )}{set $object_name = $elevatedObject.name}{/if}
{def $i18n_object_name = hash( '%objectName', concat( '"', $object_name, '"' )|wash )}

<div class="context-block a4-page">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Elevation detail for object %objectName'|i18n( 'extension/ezfind/elevate', '', $i18n_object_name )}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

{if is_set( $feedback.missing_object )}
<div class="a4-notice is-warn" role="alert"><p>{'Missing content object.'|i18n( 'extension/ezfind/elevate' )}</p></div>
{else}
{def $limit = $view_parameters.limit}

<p class="a4-intro">{'"Elevating an object" for a given search query means that this object will be returned among the very first search results when a search is triggered using this search query.'|i18n( 'extension/ezfind/elevate' )}</p>

<form name="ezfindelevationdetailform" method="post" action={'/ezfind/elevate/'|ezurl}>
<section class="a4-card" aria-labelledby="ezfind-detail-elevate-title">
    <div class="a4-card-head">
        <h2 class="a4-card-title a4-h2" id="ezfind-detail-elevate-title">{'Elevate %objectName'|i18n( 'extension/ezfind/elevate', '', $i18n_object_name )}</h2>
        <p><a href={$elevatedObject.main_node.url_alias|ezurl}>{$elevatedObject.name|wash}</a></p>
    </div>
    <div class="a4-card-body">
        <div class="a4-filterbar">
            <div class="a4-field">
                <label for="ezfind-elevate-searchquery">{'Search query'|i18n( 'extension/ezfind/elevate' )}</label>
                <input type="text" class="a4-input" id="ezfind-elevate-searchquery" name="ezfind-elevate-searchquery" value="{if is_set( $elevateSearchQuery )}{$elevateSearchQuery|wash}{/if}" title="{'Search query to elevate the object for.'|i18n( 'extension/ezfind/elevate' )|wash}" />
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
                <input type="hidden" name="redirectURI" value="{$baseurl|wash}" />
                <div class="a4-actions">
                    <button type="submit" class="a4-btn a4-btn-primary" name="ezfind-elevate-do" value="1" title="{'Store elevation'|i18n( 'extension/ezfind/elevate' )|wash}">{'Elevate'|i18n( 'extension/ezfind/elevate' )}</button>
                </div>
            </div>
        </div>
    </div>
</section>
</form>

<section class="a4-section" aria-labelledby="ezfind-detail-configurations-title">
<div class="a4-section-head">
    <h2 class="a4-h2" id="ezfind-detail-configurations-title">
        {'Existing configurations for %objectName'|i18n( 'extension/ezfind/elevate', '', $i18n_object_name )}
        {if and( is_set( $view_parameters.language ), is_set( $selectedLocale ), $selectedLocale )}{'in %selectedLanguage'|i18n( 'extension/ezfind/elevate', '', hash( '%selectedLanguage', $selectedLocale.name|wash ) )}{/if}
        {if is_set( $view_parameters.search_query )}{'containing \'%searchQuery\''|i18n( 'extension/ezfind/elevate', '', hash( '%searchQuery', $view_parameters.search_query ) )}{/if}
        {if is_set( $view_parameters.fuzzy_filter )}({'fuzzy match'|i18n( 'extension/ezfind/elevate' )}){/if}
    </h2>
</div>

<form name="ezfindelevateform" method="post" action={$baseurl|ezurl}>
<div class="a4-filterbar">
    <div class="a4-field">
        <label for="ezfind-elevationdetail-filter-searchquery">{'Search query'|i18n( 'extension/ezfind/elevate' )}</label>
        <input type="text" class="a4-input" id="ezfind-elevationdetail-filter-searchquery" name="ezfind-elevationdetail-filter-searchquery" value="{if is_set( $view_parameters.search_query )}{$view_parameters.search_query}{/if}" title="{'Search query to filter the result set on.'|i18n( 'extension/ezfind/elevate' )|wash}" />
    </div>
    <div class="a4-field">
        <label for="ezfind-elevationdetail-filter-language">{'Language'|i18n( 'extension/ezfind/elevate' )}</label>
        <select class="a4-input" id="ezfind-elevationdetail-filter-language" name="ezfind-elevationdetail-filter-language">
            <option value="{$language_wildcard|wash}"{if is_set( $view_parameters.language )|not} selected="selected"{/if}>{'All'|i18n( 'extension/ezfind/elevate' )}</option>
            {foreach $elevatedObject.languages as $lang}
            <option value="{$lang.locale|wash}"{if and( is_set( $view_parameters.language ), eq( $view_parameters.language, $lang.locale ) )} selected="selected"{/if}>{$lang.name|wash}</option>
            {/foreach}
        </select>
    </div>
    <div class="a4-field">
        <label class="a4-check"><input type="checkbox" id="ezfind-elevationdetail-filter-fuzzy" name="ezfind-elevationdetail-filter-fuzzy"{if is_set( $view_parameters.fuzzy_filter )} checked="checked"{/if} /><span>{'Fuzzy match'|i18n( 'extension/ezfind/elevate' )}</span></label>
    </div>
    <div class="a4-field">
        <div class="a4-actions">
            <button type="submit" class="a4-btn" name="ezfind-elevationdetail-filter-do" value="1" title="{'Filter configurations by language'|i18n( 'extension/ezfind/elevate' )|wash}"><span class="a4-icon a4-bi-search" aria-hidden="true"></span>{'Filter'|i18n( 'extension/ezfind/elevate' )}</button>
        </div>
    </div>
</div>
</form>

{if eq( $configurations_count, 0 )}
<p class="a4-empty">{'No existing Elevate configuration.'|i18n( 'extension/ezfind/elevate' )}</p>
{else}
<div class="a4-table-wrap">
<table class="a4-table">
    <thead>
        <tr>
            <th scope="col">{'Search query'|i18n( 'extension/ezfind/elevate' )}</th>
            <th scope="col">{'Language'|i18n( 'extension/ezfind/elevate' )}</th>
            <th scope="col"><span class="a4-sr-only">{'Actions'|i18n( 'extension/ezfind/elevate' )}</span></th>
        </tr>
    </thead>
    <tbody>
    {foreach $configurations as $conf}
        <tr>
            <td><a href={concat( $baseurl, '/(search_query)/', $conf.search_query )|ezurl} title="{'See all objects elevated by \'%searchQuery\''|i18n( 'extension/ezfind/elevate', '', hash( '%searchQuery', $conf.search_query ) )}">{$conf.search_query}</a></td>
            <td>{if eq( $conf.language_code, $language_wildcard )}<em>{'All'|i18n( 'extension/ezfind/elevate' )}</em>{else}{$conf.language_code|wash}{/if}</td>
            <td class="a4-end a4-nowrap">
                <a class="a4-icon-btn" href={concat( '/ezfind/remove_elevation/', $elevatedObject.id, '/', $conf.search_query, '/', $conf.language_code )|ezurl} title="{'Remove elevation by \'%searchQuery\' for \'%objectName\'.'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $elevatedObject.name|wash, '%searchQuery', $conf.search_query ) )}"><span class="a4-icon a4-bi-trash" aria-hidden="true"></span><span class="a4-sr-only">{'Remove elevation by \'%searchQuery\' for \'%objectName\'.'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $elevatedObject.name|wash, '%searchQuery', $conf.search_query ) )}</span></a>
            </td>
        </tr>
    {/foreach}
    </tbody>
</table>
</div>

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
             page_uri=$baseurl
             item_count=$configurations_count
             view_parameters=$view_parameters
             item_limit=$limit}
    </div>
</div>
{/if}
</section>
{undef $limit}
{/if}

</div></div></div>
</div>
