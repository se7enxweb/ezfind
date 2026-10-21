<?php
/**
 * The eZ Find administration pages in the admin4 design: the Elevate views have an admin4 template each, built from
 * the design's components, with the same form fields the views read; the left menu is a menu.ini section; and the
 * German translation leaves no message of these pages, or of the admin search page, unfinished. Reads the files only,
 * without a kernel, a database or Solr:
 *
 *   php vendor/bin/phpunit extension/ezfind/tests/classes/ezfAdmin4TemplatesTest.php
 *
 *  A4-01 - Every administration view (a view of modules/ezfind with the eZ Find left menu) has its template and the
 *          left menu in admin4
 *  A4-02 - The admin4 templates carry no inline CSS, no table layout attributes and no images for icons
 *  A4-03 - A value from the request or from a stored configuration is printed washed, except the search query: the
 *          views pass it, and store it, encoded with htmlspecialchars() already, and washing it again breaks it
 *  A4-04 - Every form field and button the standard template posts is in the admin4 template too
 *  A4-05 - menu.ini has the left menu [Leftmenu_ezfind] with the Elevate view, limited like the top menu tab; the
 *          standard design's left menu shows the link only with the policy ezfind/elevate
 *  A4-06 - ger-DE translates every string of the admin4 templates and leaves design/admin/content/search complete:
 *          an unfinished message in an extension's translation is read as its English source and hides the
 *          kernel's translation of the same message
 *  A4-07 - A toggle is a button with aria-pressed, and every icon button is named by what it acts on
 *  A4-08 - The eng-US translation spells Synchronize for the British sources
 *  A4-09 - ger-DE leaves no current message unfinished, every message is in the untranslated sources, and the
 *          credit line has the source ezsolr.php translates
 *  A4-10 - A confirmed removal needs its search query and its language, and matches the stored encoded query
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package tests
 */

use PHPUnit\Framework\TestCase;

class ezfAdmin4TemplatesTest extends TestCase
{
    private static function root(): string
    {
        return dirname( __DIR__, 2 );
    }

    /** @return array<string, string> file name => template source without template comments */
    private static function admin4Templates(): array
    {
        $templates = array();
        foreach ( glob( self::root() . '/design/admin4/templates/ezfind/*.tpl' ) ?: array() as $file )
        {
            $templates[basename( $file )] = (string)preg_replace( '/\{\*.*?\*\}/s', '', (string)file_get_contents( $file ) );
        }
        ksort( $templates );
        return $templates;
    }

    /** @return array<int, string> the names of the form fields and buttons in a template */
    private static function formNames( string $source ): array
    {
        preg_match_all( '/\bname="([^"{]+)"/', $source, $matches );
        $names = array_values( array_unique( $matches[1] ) );
        sort( $names );
        return $names;
    }

    /** @return array<string, array<string, array{0: string|null, 1: string}>> context => source => (type, translation) */
    private static function translations( string $language ): array
    {
        $document = new DOMDocument();
        $document->load( self::root() . "/translations/$language/translation.ts" );
        $result = array();
        foreach ( $document->getElementsByTagName( 'context' ) as $context )
        {
            $name = $context->getElementsByTagName( 'name' )->item( 0 )->textContent;
            foreach ( $context->getElementsByTagName( 'message' ) as $message )
            {
                $translation = $message->getElementsByTagName( 'translation' )->item( 0 );
                $result[$name][$message->getElementsByTagName( 'source' )->item( 0 )->textContent] = array(
                    $translation && $translation->hasAttribute( 'type' ) ? $translation->getAttribute( 'type' ) : null,
                    $translation ? $translation->textContent : '',
                );
            }
        }
        return $result;
    }

    /** @return array<int, string> the templates of the views that draw the eZ Find left menu, and the menu itself */
    private static function administrationTemplates(): array
    {
        $templates = array();
        foreach ( glob( self::root() . '/modules/ezfind/*.php' ) ?: array() as $view )
        {
            $source = (string)file_get_contents( $view );
            if ( strpos( $source, 'design:ezfind/backoffice_left_menu.tpl' ) === false )
            {
                continue;
            }
            preg_match_all( '/fetch\(\s*["\']design:ezfind\/([\w.]+\.tpl)["\']/', $source, $matches );
            $templates = array_merge( $templates, $matches[1] );
        }
        $templates[] = 'backoffice_left_menu.tpl';
        $templates = array_values( array_unique( $templates ) );
        sort( $templates );
        return $templates;
    }

    public function testEveryAdministrationViewHasAnAdmin4Template(): void
    {
        $templates = self::administrationTemplates();
        $this->assertContains( 'elevate.tpl', $templates );
        $this->assertSame( array(), array_values( array_diff( $templates, array_keys( self::admin4Templates() ) ) ) );
    }

    public function testTheAdmin4TemplatesCarryNoInlineCss(): void
    {
        foreach ( self::admin4Templates() as $file => $source )
        {
            $this->assertDoesNotMatchRegularExpression( '/<style\b/i', $source, $file );
            $this->assertDoesNotMatchRegularExpression( '/(?<![\w.\-])style\s*=/i', $source, $file );
            $this->assertDoesNotMatchRegularExpression( '/\b(cellspacing|width)\s*=/i', $source, $file );
            $this->assertDoesNotMatchRegularExpression( '/<img\b/i', $source, $file );
            $this->assertStringNotContainsString( 'box-tc', $source, "$file: the admin4 frame has no corner boxes" );
        }
    }

    public function testValuesFromTheRequestOrTheStoreArePrintedWashed(): void
    {
        $encoded = array( '{$view_parameters.search_query}', '{$conf.search_query}' );
        foreach ( self::admin4Templates() as $file => $source )
        {
            // a variable printed as it is: {$feedback.x}, {$lang.name} ...; the search query comes encoded
            preg_match_all( '/\{\$(view_parameters|conf|feedback|elevateSearchQuery|lang|translation|elevatedObject|tmp_obj|object|baseurl|language_wildcard)\b[^}|]*\}/', str_replace( $encoded, '', $source ), $raw );
            $this->assertSame( array(), $raw[0], "$file prints a value without |wash" );
            if ( $file !== 'remove_elevation.tpl' )
            {
                $this->assertStringNotContainsString( 'search_query|wash', $source, "$file washes the encoded search query a second time" );
            }
        }
    }

    public function testTheAdmin4TemplatesPostTheSameFields(): void
    {
        foreach ( array( 'elevate.tpl', 'elevation_detail.tpl', 'remove_elevation.tpl' ) as $file )
        {
            $standard = self::formNames( (string)file_get_contents( self::root() . "/design/standard/templates/ezfind/$file" ) );
            $admin4 = self::formNames( self::admin4Templates()[$file] );
            $this->assertSame( array(), array_values( array_diff( $standard, $admin4 ) ), "$file: fields of the standard template missing in admin4" );
        }
    }

    public function testMenuIniHasTheLeftMenu(): void
    {
        $ini = (string)file_get_contents( self::root() . '/settings/menu.ini.append.php' );
        $this->assertMatchesRegularExpression( '/^\[Leftmenu_ezfind\]$/m', $ini );
        $rest = substr( $ini, strpos( $ini, '[Leftmenu_ezfind]' ) + strlen( '[Leftmenu_ezfind]' ) );
        $section = preg_split( '/^(?:\[|\*\/)/m', $rest )[0];
        $this->assertMatchesRegularExpression( '/^Links\[elevate\]=ezfind\/elevate$/m', $section );
        $this->assertMatchesRegularExpression( '/^LinkNames\[elevate\]=\S/m', $section );
        $this->assertMatchesRegularExpression( '/^PolicyList\[\]=ezfind\/elevate$/m', $section );
        $this->assertStringContainsString( "ini_section='Leftmenu_ezfind'", self::admin4Templates()['backoffice_left_menu.tpl'] );
        $this->assertStringContainsString( 'design:parts/left_width_control.tpl', self::admin4Templates()['backoffice_left_menu.tpl'] );

        $standard = (string)preg_replace( '/\{\*.*?\*\}/s', '', (string)file_get_contents( self::root() . '/design/standard/templates/ezfind/backoffice_left_menu.tpl' ) );
        $this->assertMatchesRegularExpression(
            "/\\{if fetch\\( 'user', 'has_access_to', hash\\( 'module', 'ezfind', 'function', 'elevate' \\) \\)\\}\\s*<li><div><a href=\\{'\\/ezfind\\/elevate\\/'\\|ezurl\\}/",
            $standard
        );
    }

    /**
     * A4-07 - A control that switches a state is a button with aria-pressed, not a link with a button role, and every
     *         icon button is named by what it acts on (not a bare "Edit" or "Trash" repeated in every row)
     */
    public function testTogglesAreButtonsAndIconButtonsHaveFullNames(): void
    {
        foreach ( self::admin4Templates() as $file => $source )
        {
            $this->assertDoesNotMatchRegularExpression( '/<a\b[^>]*role="button"/', $source, "$file: a link with a button role" );
            preg_match_all( '/<a class="a4-icon-btn"[^>]*>.*?<\/a>/s', $source, $buttons );
            foreach ( $buttons[0] as $button )
            {
                $this->assertMatchesRegularExpression( '/<span class="a4-sr-only">\{\'(?!Edit\'|Trash\')[^\']{12,}/', $button, "$file: icon button without a full name" );
            }
            if ( preg_match_all( '/<button\b[^>]*name="Value"[^>]*>/', $source, $toggles ) )
            {
                foreach ( $toggles[0] as $toggle )
                {
                    $this->assertMatchesRegularExpression( '/aria-pressed="(true|false)"/', $toggle, "$file: a toggle without its state" );
                }
            }
        }
    }

    /** A4-08 - American English: the eng-US translation spells Synchronize for the British sources */
    public function testAmericanEnglishSpellsSynchronize(): void
    {
        $english = self::translations( 'eng-US' );
        foreach ( array( 'Synchronise', 'Synchronise Elevate configuration with Solr', 'Synchronise the Elevate configuration with Solr.' ) as $text )
        {
            $entry = $english['extension/ezfind/elevate'][$text] ?? null;
            $this->assertNotNull( $entry, $text );
            $this->assertStringContainsString( 'Synchronize', $entry[1] );
        }
    }

    public function testGermanTranslatesThePages(): void
    {
        $german = self::translations( 'ger-DE' );
        $missing = array();
        foreach ( self::admin4Templates() as $file => $source )
        {
            preg_match_all( "/\\{(['\"])((?:(?!\\1).|\\\\\\1)*)\\1\\|i18n\\(\\s*'([^']+)'/", $source, $matches, PREG_SET_ORDER );
            foreach ( $matches as $match )
            {
                $text = str_replace( array( "\\'", '\\"' ), array( "'", '"' ), $match[2] );
                $entry = $german[$match[3]][$text] ?? null;
                if ( $entry === null || $entry[0] === 'unfinished' || trim( $entry[1] ) === '' )
                {
                    $missing[] = "$file: {$match[3]}: $text";
                }
            }
        }
        foreach ( array( 'Elevation', 'eZ Find' ) as $text )
        {
            $entry = $german['design/admin/parts/ezfind/menu'][$text] ?? null;
            if ( $entry === null || $entry[0] === 'unfinished' || trim( $entry[1] ) === '' )
            {
                $missing[] = "menu.ini: design/admin/parts/ezfind/menu: $text";
            }
        }
        foreach ( $german['design/admin/content/search'] ?? array() as $text => $entry )
        {
            if ( $entry[0] === 'unfinished' || trim( $entry[1] ) === '' )
            {
                $missing[] = "design/admin/content/search: $text";
            }
        }
        $this->assertSame( array(), $missing );
    }

    /**
     * A4-09 - ger-DE leaves no current message unfinished or empty (obsolete ones aside), every message of it is in the
     * untranslated source file too, and the source of the credit line is the text ezsolr.php translates
     */
    public function testGermanIsCompleteAndMatchesTheSources(): void
    {
        $german = self::translations( 'ger-DE' );
        $sources = self::translations( 'untranslated' );
        $unfinished = array();
        $unknown = array();
        foreach ( $german as $context => $messages )
        {
            foreach ( $messages as $text => $entry )
            {
                if ( $entry[0] === 'obsolete' )
                {
                    continue;
                }
                if ( $entry[0] === 'unfinished' || trim( $entry[1] ) === '' )
                {
                    $unfinished[] = "$context: $text";
                }
                if ( !isset( $sources[$context][$text] ) )
                {
                    $unknown[] = "$context: $text";
                }
            }
        }
        $this->assertSame( array(), $unfinished, 'unfinished in ger-DE' );
        $this->assertSame( array(), $unknown, 'in ger-DE but not in untranslated' );

        preg_match( "/ezpI18n::tr\\(\\s*'ezfind',\\s*'(eZ Find %version search plugin[^']*)'/", file_get_contents( self::root() . '/search/plugins/ezsolr/ezsolr.php' ), $match );
        $this->assertNotEmpty( $match, 'credit line in ezsolr.php' );
        $this->assertArrayHasKey( $match[1], $german['ezfind'] );
        $this->assertArrayHasKey( $match[1], $sources['ezfind'] );
    }

    /**
     * A4-10 - A confirmed removal needs its search query and its language (purge() with an empty one removes every
     * configuration of the object) and matches the stored, encoded search query whether it was posted encoded or not
     */
    public function testRemovalNeedsTheQueryAndTheLanguage(): void
    {
        $source = file_get_contents( self::root() . '/modules/ezfind/remove_elevation.php' );
        $guard = strpos( $source, "trim( (string)\$http->postVariable( 'ezfind-removeelevation-searchquery', '' ) ) === ''" );
        $purge = strpos( $source, 'eZFindElevateConfiguration::purge(' );
        $this->assertNotFalse( $guard );
        $this->assertNotFalse( strpos( $source, "trim( (string)\$http->postVariable( 'ezfind-removeelevation-languagecode', '' ) ) === ''" ) );
        $this->assertLessThan( $purge, $guard, 'the guard comes before the purge' );
        $this->assertStringContainsString( "htmlspecialchars( htmlspecialchars_decode(", $source );
        // the confirmation shows the query as entered, washed once, not the stored encoded form washed again
        $this->assertSame( 2, substr_count( $source, "'search_query_text' => htmlspecialchars_decode(" ) );
        $this->assertStringContainsString( '{$feedback.confirm_remove.search_query_text|wash}', file_get_contents( self::root() . '/design/admin4/templates/ezfind/remove_elevation.tpl' ) );

        foreach ( array( 'a&b "c"', 'a&amp;b &quot;c&quot;' ) as $posted )
        {
            $this->assertSame( htmlspecialchars( 'a&b "c"', ENT_QUOTES ), htmlspecialchars( htmlspecialchars_decode( $posted, ENT_QUOTES ), ENT_QUOTES ) );
        }
    }
}
