<?php /*

[NavigationPart]
Part[ezfindnavigationpart]=eZFind

[TopAdminMenu]
Tabs[]=ezfind

[Topmenu_ezfind]
NavigationPartIdentifier=ezfindnavigationpart
Name=eZ Find
Tooltip=eZ Find dashboard
URL[]
URL[default]=ezfind/elevate
Enabled[]
Enabled[default]=true
Enabled[browse]=false
Enabled[edit]=false
Shown[]
Shown[default]=true
Shown[edit]=true
Shown[navigation]=true
# We don't show it in browse mode
Shown[browse]=true
PolicyList[]=ezfind/elevate

# The left menu of the eZ Find pages in the admin4 design (design/admin4/templates/ezfind/backoffice_left_menu.tpl,
# drawn by parts/ini_menu.tpl). Name and LinkNames are translated in the context design/admin/parts/ezfind/menu.
[Leftmenu_ezfind]
Name=eZ Find
Links[]
Links[elevate]=ezfind/elevate
LinkNames[]
LinkNames[elevate]=Elevation
PolicyList[]
PolicyList[]=ezfind/elevate

*/ ?>
