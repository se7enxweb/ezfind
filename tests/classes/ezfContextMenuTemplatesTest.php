<?php
/**
 * The eZ Find entries of the content tree and sub-items context menus are declared in data-menu-* attributes, so
 * they open on an administration page under a Content Security Policy without 'unsafe-inline'. Reads the files
 * only, without a kernel, a database or Solr:
 *
 *   php vendor/bin/phpunit extension/ezfind/tests/classes/ezfContextMenuTemplatesTest.php
 *
 *  CM-01 - The menu templates carry no script element and no event handler attribute
 *  CM-02 - The eZ Find item of both menus opens the submenu eZFind, and the submenu declares its depth
 *  CM-03 - Every form a submenu item submits is in the context menu template, and admininterface.ini lists the
 *          three templates
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package tests
 */

use PHPUnit\Framework\TestCase;

class ezfContextMenuTemplatesTest extends TestCase
{
    const TEMPLATES = array( 'ezfindcontextmenu.tpl', 'ezfindsubitemscontextmenu.tpl', 'ezfindcontextsubmenu.tpl' );

    private static function source( string $name ): string
    {
        $source = (string)file_get_contents( dirname( __DIR__, 2 ) . '/design/standard/templates/node/' . $name );
        // template comments are not output
        return (string)preg_replace( '/\{\*.*?\*\}/s', '', $source );
    }

    /** CM-01 */
    public function testNoInlineScriptOrHandler()
    {
        foreach ( self::TEMPLATES as $name )
        {
            $source = self::source( $name );
            $this->assertDoesNotMatchRegularExpression( '/<script\b/i', $source, $name );
            $this->assertDoesNotMatchRegularExpression( '/\son[a-z]+\s*=/i', $source, $name );
            $this->assertStringNotContainsString( 'javascript:', $source, $name );
        }
    }

    /** CM-02 */
    public function testSubmenuIsDeclared()
    {
        $this->assertMatchesRegularExpression( '/<a id="menu-ezfind"[^>]*data-menu-submenu="eZFind"/', self::source( 'ezfindcontextmenu.tpl' ) );
        $this->assertMatchesRegularExpression( '/<a id="child-menu-ezfind"[^>]*data-menu-submenu="eZFind"/', self::source( 'ezfindsubitemscontextmenu.tpl' ) );
        $this->assertMatchesRegularExpression( '/<div class="popupmenu" id="eZFind" data-menu-depth="1">/', self::source( 'ezfindcontextsubmenu.tpl' ) );
    }

    /** CM-03 */
    public function testSubmittedFormsExist()
    {
        preg_match_all( '/data-menu-form="([^"]+)"/', self::source( 'ezfindcontextsubmenu.tpl' ), $matches );
        $this->assertSame( array( 'ezfind-menu-form-elevate', 'ezfind-menu-form-elevation-detail' ), $matches[1] );
        $menu = self::source( 'ezfindcontextmenu.tpl' );
        foreach ( $matches[1] as $form )
        {
            $this->assertStringContainsString( '<form id="' . $form . '"', $menu );
        }
        $ini = (string)file_get_contents( dirname( __DIR__, 2 ) . '/settings/admininterface.ini.append.php' );
        $this->assertStringContainsString( 'ContextMenuTemplateArray[]=node/ezfindcontextmenu.tpl', $ini );
        $this->assertStringContainsString( 'SubitemsContextMenuTemplateArray[]=node/ezfindsubitemscontextmenu.tpl', $ini );
        $this->assertStringContainsString( 'SubMenuTemplateArray[]=node/ezfindcontextsubmenu.tpl', $ini );
    }
}
