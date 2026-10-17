<?php
/**
 * @package ezfind
 * @class   ezfindInfo
 **/

class ezfindInfo
{
    public static function info()
    {
        return array(
            'Name'      => '<a href="https://github.com/se7enxweb/ezfind">eZ Find LS : Solr search for Exponential</a>',
            'Version'   => "3.0.6",
            'Copyright' => 'Copyright &copy; 1998 - ' . date( 'Y' ) . ' <a href="https://se7enx.com" target="blank">7x</a> &amp; Exponential Foundation. Copyright &copy; 1999-2014 eZ Systems AS',
            'License'   => "GNU General Public License v2.0 (or any later version)",
            'Info_url'  => 'https://github.com/se7enxweb/ezfind',
            '3rdparty_software' => array(
                'name'      => 'Apache Solr',
                'Version'   => '4.10.1',
                'copyright' => 'The Apache Software Foundation',
                'license'   => 'Apache License, Version 2.0',
                'info_url'  => 'https://solr.apache.org/'
            )
        );
    }
}
