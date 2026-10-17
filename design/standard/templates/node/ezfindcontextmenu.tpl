{* The eZ Find submenu (node/ezfindcontextsubmenu.tpl) is declared in data-menu-* attributes, no inline script
   (doc/features/6.0/content-security-policy.md) *}
 <hr/>
    <a id="menu-ezfind" class="more" href="#" data-menu-submenu="eZFind">{'eZ Find'|i18n( 'extension/ezfind/popupmenu' )}</a>

{* Elevate object *}
<form id="ezfind-menu-form-elevate" method="post" action={"/ezfind/elevate/"|ezurl}>
  <input type="hidden" name="ObjectIDFromMenu" value="%objectID%" />
</form>

{* Elevation detail for object *}
<form id="ezfind-menu-form-elevation-detail" method="post" action={"/ezfind/elevation_detail/"|ezurl}>
  <input type="hidden" name="ObjectID" value="%objectID%" />
</form>