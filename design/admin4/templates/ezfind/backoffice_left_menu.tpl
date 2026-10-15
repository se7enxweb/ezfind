{* The left menu of the eZ Find pages (the views of modules/ezfind set it as $Result['left_menu']) in the admin4
   design: the links of menu.ini [Leftmenu_ezfind], drawn by the design's own menu template (parts/ini_menu.tpl:
   access per link, the current page marked, names translated in the context design/admin/parts/ezfind/menu), and
   the width control every admin4 left menu ends with. *}
{include uri='design:parts/ini_menu.tpl' ini_section='Leftmenu_ezfind'}

{include uri='design:parts/left_width_control.tpl'}
