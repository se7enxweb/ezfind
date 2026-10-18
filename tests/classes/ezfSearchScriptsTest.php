<?php
/**
 * The search suggestions and the AJAX search of eZ Find run without YUI, as plain JavaScript: an accessible
 * combobox against the server function ezfind::autocomplete, tested in node with a small DOM against a stubbed
 * endpoint. Reads files and runs node only, without a kernel, a database or Solr:
 *
 *   php vendor/bin/phpunit extension/ezfind/tests/classes/ezfSearchScriptsTest.php
 *
 *  SS-01 - The suggestions (tests/js/autocomplete.js, AC-01 to AC-09): combobox markup, debounced lookups on the
 *          server function's URL, rendering, arrow keys, Enter, Escape, click, late answers, errors
 *  SS-02 - The AJAX search of the ezflow search block (tests/js/ajaxsearch.js, AS-01 and AS-02)
 *  SS-03 - Nothing in the extension refers to YUI any more (the changelogs of earlier versions aside)
 *  SS-04 - Every search field with suggestions has an id, loads ezajax_autocomplete.js and its stylesheet's classes
 *          exist, and the templates carry no inline script or style
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package tests
 */

use PHPUnit\Framework\TestCase;

class ezfSearchScriptsTest extends TestCase
{
    const TEMPLATES = array(
        'design/admin/templates/page_search.tpl',
        'design/admin/templates/content/search.tpl',
        'design/ezwebin/templates/page_header_searchbox.tpl',
        'design/ezwebin/templates/content/search.tpl',
        'design/ezdemo/templates/content/search.tpl',
        'design/ezflow/override/templates/block/search.tpl',
    );

    private static function root(): string
    {
        return dirname( __DIR__, 2 );
    }

    private function runNode( string $script ): void
    {
        $node = trim( (string)shell_exec( 'command -v node 2>/dev/null' ) );
        if ( $node === '' )
        {
            $this->markTestSkipped( 'node is not installed' );
        }
        $output = array();
        $status = 0;
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( self::root() . '/tests/js/' . $script ) . ' 2>&1', $output, $status );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $status, $text );
        $this->assertStringNotContainsString( 'FAIL ', $text );
        $this->assertStringContainsString( 'PASS ', $text );
    }

    /** SS-01 */
    public function testAutocompleteCombobox()
    {
        $this->runNode( 'autocomplete.js' );
    }

    /** SS-02 */
    public function testAjaxSearch()
    {
        $this->runNode( 'ajaxsearch.js' );
    }

    /** SS-03 */
    public function testNoYuiLeft()
    {
        $root = self::root();
        $found = array();
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $files as $file )
        {
            $relative = substr( $file->getPathname(), strlen( $root ) + 1 );
            if ( preg_match( '#^(\.git/|doc/changelogs/|tests/|java/)#', $relative ) || $file->getSize() > 2000000 )
            {
                continue;
            }
            if ( preg_match( '/yui/i', (string)file_get_contents( $file->getPathname() ) ) )
            {
                $found[] = $relative;
            }
        }
        $this->assertSame( array(), $found, 'files that still refer to YUI' );
    }

    /** SS-04 */
    public function testTemplatesLoadTheCombobox()
    {
        $css = (string)file_get_contents( self::root() . '/design/admin/stylesheets/ezajax_autocomplete.css' );
        foreach ( array( 'ezfind-ac-list', 'ezfind-ac-option', 'is-active', 'ezfind-ac-status', 'ezfind-ac-anchor' ) as $class )
        {
            $this->assertStringContainsString( $class, $css );
            foreach ( array( 'ezwebin', 'ezflow' ) as $design )
            {
                $this->assertStringContainsString( $class, (string)file_get_contents( self::root() . "/design/$design/stylesheets/ezajax_autocomplete.css" ) );
            }
        }
        foreach ( self::TEMPLATES as $name )
        {
            $source = (string)preg_replace( '/\{\*.*?\*\}/s', '', (string)file_get_contents( self::root() . '/' . $name ) );
            $this->assertMatchesRegularExpression( '/<input\b[^>]*\bid="[^"]+"[^>]*\bdata-ezfind-autocomplete="/', $source, $name );
            $this->assertMatchesRegularExpression( "/ezscript(?:_require)?\\(\\s*array\\([^)]*'ezajax_autocomplete\\.js'/", $source, $name );
            $this->assertStringNotContainsString( 'ezjsc::yui', $source, $name );
            $this->assertDoesNotMatchRegularExpression( '/<script\b/i', $source, $name );
            $this->assertDoesNotMatchRegularExpression( '/\sstyle\s*=/i', $source, $name );
            $this->assertDoesNotMatchRegularExpression( '/\son[a-z]+\s*=/i', $source, $name );
        }
    }
}
