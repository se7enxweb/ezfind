{* Removing one Elevate configuration (ezfind/remove_elevation) in the admin4 design: the confirmation, and after it
   the confirmed removal with a link back. The same view as design/standard/templates/ezfind/remove_elevation.tpl,
   with the same form fields and buttons, built from the admin4 components. No inline CSS. *}
{def $object_link = concat( '<a href=', $elevatedObject.main_node.url_alias|ezurl, '>', $elevatedObject.name|wash, '</a>' )}

<div class="context-block a4-page">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">
{if is_set( $feedback.removal_back_link )}
    {'Removal confirmed ( for %objectName ) :'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $object_link ) )}
{else}
    {'Confirm removal of the following Elevate configuration ( for %objectName ) :'|i18n( 'extension/ezfind/elevate', '', hash( '%objectName', $object_link ) )}
{/if}
</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

{if is_set( $feedback.removal_back_link )}
<div class="a4-notice is-ok" role="status"><p>{'The Elevate configuration was removed.'|i18n( 'extension/ezfind/elevate' )}</p></div>
{/if}

<form name="ezfindremoveelevationform" method="post" action={concat( '/ezfind/remove_elevation/', $elevatedObject.id )|ezurl}>
<input type="hidden" name="ezfind-removeelevation-objectid" value="{$elevatedObject.id|wash}" />
<input type="hidden" name="ezfind-removeelevation-searchquery" value="{$feedback.confirm_remove.search_query|wash}" />
<input type="hidden" name="ezfind-removeelevation-languagecode" value="{$feedback.confirm_remove.language_code|wash}" />

<div class="a4-table-wrap">
<table class="a4-table">
    <thead>
        <tr>
            <th scope="col">{'Search query'|i18n( 'extension/ezfind/elevate' )}</th>
            <th scope="col">{'Language'|i18n( 'extension/ezfind/elevate' )}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><a href={concat( '/ezfind/elevate/(search_query)/', $feedback.confirm_remove.search_query )|ezurl} title="{'See all objects elevated by \'%searchQuery\''|i18n( 'extension/ezfind/elevate', '', hash( '%searchQuery', $feedback.confirm_remove.search_query_text ) )|wash}">{$feedback.confirm_remove.search_query_text|wash}</a></td>
            <td>{if eq( $feedback.confirm_remove.language_code, $language_wildcard )}<em>{'All'|i18n( 'extension/ezfind/elevate' )}</em>{else}{$feedback.confirm_remove.language_code|wash}{/if}</td>
        </tr>
    </tbody>
</table>
</div>

<div class="a4-toolbar is-bottom">
    <div class="a4-actions">
    {if is_set( $feedback.removal_back_link )}
        <a class="a4-btn" href={$feedback.removal_back_link|ezurl}>{'Back'|i18n( 'extension/ezfind/elevate' )}</a>
    {else}
        <button type="submit" class="a4-btn a4-btn-danger" name="ezfind-removeelevation-do" value="1" title="{'Confirm removal of the elevate configuration'|i18n( 'extension/ezfind/elevate' )|wash}">{'Remove'|i18n( 'extension/ezfind/elevate' )}</button>
        <button type="submit" class="a4-btn" name="ezfind-removeelevation-cancel" value="1" title="{'Cancel removal of the elevate configuration'|i18n( 'extension/ezfind/elevate' )|wash}">{'Cancel'|i18n( 'extension/ezfind/elevate' )}</button>
    {/if}
    </div>
</div>
</form>

</div></div></div>
</div>
