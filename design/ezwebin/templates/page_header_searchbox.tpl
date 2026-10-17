<div id="searchbox">
  <form action={"/content/search"|ezurl}>
    <label for="searchtext" class="hide">{'Search text:'|i18n('design/ezwebin/pagelayout')}</label>
    {if $pagedata.is_edit}
    <input id="searchtext" name="SearchText" type="text" value="" size="12" aria-label="{'Search'|i18n('design/ezwebin/pagelayout')}" disabled="disabled" />
    <input id="searchbutton" class="button-disabled" type="submit" value="{'Search'|i18n('design/ezwebin/pagelayout')}" alt="{'Search'|i18n('design/ezwebin/pagelayout')}" disabled="disabled" />
    {else}
    <div class="yui3-skin-sam ez-autocomplete">
        <input id="searchtext" name="SearchText" type="text" value="" size="12" aria-label="{'Search'|i18n('design/ezwebin/pagelayout')}" data-ezfind-autocomplete="{json_encode( hash( 'url', 'ezjscore/call/ezfind::autocomplete'|ezurl( 'no' ), 'minQueryLength', ezini( 'AutoCompleteSettings', 'MinQueryLength', 'ezfind.ini' )|int, 'resultLimit', ezini( 'AutoCompleteSettings', 'Limit', 'ezfind.ini' )|int ) )|wash}" />
        <input id="searchbutton" class="button" type="submit" value="{'Search'|i18n('design/ezwebin/pagelayout')}" alt="{'Search'|i18n('design/ezwebin/pagelayout')}" />
    </div>
    {if eq( $ui_context, 'browse' )}
     <input name="Mode" type="hidden" value="browse" />
    {/if}

    {/if}
  </form>
</div>

{if $pagedata.is_edit|not()}

{* The autocomplete of the search field starts from data-ezfind-autocomplete (javascript/ezajax_autocomplete.js; no inline code). *}
{ezscript_require( array('ezjsc::yui3', 'ezajax_autocomplete.js') )}
{ezcss_require( 'ezajax_autocomplete.css' )}

{/if}
