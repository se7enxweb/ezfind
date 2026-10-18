<h2>{$block.name|wash()}</h2>
<form id="search-form-{$block.id}" action="{'content/search'|ezurl('no')}" method="post">
<div class="ez-autocomplete">
    {* The autocomplete starts from data-ezfind-autocomplete (javascript/ezajax_autocomplete.js: an accessible combobox, no inline code). *}
    <input id="search-string-{$block.id}" type="text" name="SearchStr" value="" aria-label="{'Search'|i18n( 'design/ezflow/block/search' )}" data-ezfind-autocomplete="{json_encode( hash( 'url', 'ezjscore/call/ezfind::autocomplete'|ezurl( 'no' ), 'minQueryLength', ezini( 'AutoCompleteSettings', 'MinQueryLength', 'ezfind.ini' )|int, 'resultLimit', ezini( 'AutoCompleteSettings', 'Limit', 'ezfind.ini' )|int, 'statusText', '%count suggestions'|i18n( 'extension/ezfind/autocomplete' ), 'noResultsText', 'No suggestions'|i18n( 'extension/ezfind/autocomplete' ) ) )|wash}" />
    <input id="search-button-{$block.id}" class="button" type="submit" name="SearchButton" value="{'Search'|i18n( 'design/ezflow/block/search' )}" />
    {def $customAttributes = array()}
    {foreach $block.custom_attributes as $name => $value}
        <input id="search-custom-attribute-{$name}-{$block.id}" type="hidden" name="{$name}" value="{$value}" />
        {set $customAttributes = $customAttributes|merge( array( concat( '#search-custom-attribute-', $name, '-', $block.id ) ) )}
    {/foreach}
</div>
</form>

<div id="search-results-{$block.id}"></div>
{ezscript_require( array( 'ezajax_autocomplete.js', 'ezajaxsearch.js' ) )}
{ezcss_require( 'ezajax_autocomplete.css' )}

{* The AJAX search settings, read by ezajaxsearch.js when the page is ready (no inline code). *}
{set-block variable=ezfind_spellcheck_template}<div id="spellcheck-{$block.id}" class="ajax-search-spellcheck">{'Did you mean'|i18n( 'extension/ezfind/ajax-search' )}"<a>{ldelim}spellcheck{rdelim}</a>"?</div>{/set-block}
{set-block variable=ezfind_facets_main_template}<div class="ajax-search-facets" id="facets-{$block.id}">{'Refine with facets'|i18n( 'extension/ezfind/ajax-search' )}<ul>{ldelim}inner_facet_list{rdelim}</ul></div>{/set-block}
{set-block variable=ezfind_facets_inner_template}<li>{ldelim}facet_name{rdelim}</li><ul>{ldelim}facet_element{rdelim}</ul>{/set-block}
{set-block variable=ezfind_facets_element_template}<li><a href="{ldelim}link{rdelim}">{ldelim}value{rdelim}</a> {ldelim}count{rdelim}</li>{/set-block}
{set-block variable=ezfind_result_template}<div class="result-item float-break"><div class="item-title"><a href="{ldelim}url_alias{rdelim}">{ldelim}title{rdelim}</a></div><div class="item-published-date">[{ldelim}class_name{rdelim}] {ldelim}date{rdelim}</div></div>{/set-block}
<div hidden="hidden"
     data-ezflow-ajaxsearch="1"
     data-call-url="{'ezjscore/call'|ezurl( 'no' )|wash}"
     data-custom-search-attributes="{json_encode( $customAttributes )|wash}"
     data-noresult-string="{'No search results...'|i18n( 'extension/ezfind/ajax-search' )|wash}"
     data-search-string="#search-string-{$block.id|wash}"
     data-search-button="#search-button-{$block.id|wash}"
     data-search-results="#search-results-{$block.id|wash}"
     data-date-format="shortdatetime"
     data-spellcheck="#spellcheck-{$block.id|wash} a"
     data-spellcheck-template="{$ezfind_spellcheck_template|wash}"
     data-facets-main-template="{$ezfind_facets_main_template|wash}"
     data-facets-inner-template="{$ezfind_facets_inner_template|wash}"
     data-facets-element-template="{$ezfind_facets_element_template|wash}"
     data-result-template="{$ezfind_result_template|wash}"></div>
{undef $customAttributes}
