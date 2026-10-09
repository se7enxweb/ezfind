<?php
/**
 * The constructors of eZ Find on PHP 8, which no longer calls a method named after its class on "new". Without a
 * database and without Solr; runs with the PHPUnit bootstrap of an Exponential kernel, from its root:
 *
 *   php vendor/bin/phpunit extension/ezfind/tests/classes/ezfPhp8ConstructorsTest.php
 *
 * The extension need not be active: the eZ Find classes are loaded from this checkout when the autoloads do not
 * know them.
 *
 *  PC-01 - Every class has __construct() as its constructor; the old name stays a method that calls it
 *  PC-02 - Each constructor runs with the arguments eZ Find passes and sets up what the class needs
 *  PC-03 - No class of the extension has a method named after its class that is not a wrapper of __construct()
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package tests
 */

$ezfindRoot = dirname( __DIR__, 2 );
foreach ( array( 'ezfSolrDocumentFieldName' => 'classes/ezfsolrdocumentfieldname.php',
                 'ezfSolrDocumentFieldBase' => 'classes/ezfsolrdocumentfieldbase.php',
                 'eZSolr' => 'search/plugins/ezsolr/ezsolr.php',
                 'ezfeZPSolrQueryBuilder' => 'classes/ezfezpsolrquerybuilder.php',
                 'eZFindResultObject' => 'classes/ezfindresultobject.php',
                 'eZFindResultNode' => 'classes/ezfindresultnode.php',
                 'ezfSearchResultInfo' => 'classes/ezfsearchresultinfo.php',
                 'ezfModuleFunctionCollection' => 'classes/ezfmodulefunctioncollection.php' ) as $ezfindClass => $ezfindFile )
{
    if ( !class_exists( $ezfindClass ) )
    {
        require_once $ezfindRoot . '/' . $ezfindFile;
    }
}
unset( $ezfindRoot, $ezfindClass, $ezfindFile );

class ezfPhp8ConstructorsTest extends PHPUnit\Framework\TestCase
{
    /**
     * The class of bin/php/updatesearchindexsolr.php, which runs the indexing when the file is included: its
     * class declaration alone, read from the file
     */
    private static function loadUpdateSearchIndexClass()
    {
        if ( class_exists( 'ezfUpdateSearchIndexSolr', false ) )
        {
            return;
        }
        $source = file_get_contents( dirname( __DIR__, 2 ) . '/bin/php/updatesearchindexsolr.php' );
        $tokens = token_get_all( $source );
        $code = '';
        $depth = 0;
        $inClass = false;
        foreach ( $tokens as $i => $token )
        {
            $text = is_array( $token ) ? $token[1] : $token;
            if ( !$inClass && is_array( $token ) && $token[0] === T_CLASS )
            {
                $inClass = true;
            }
            if ( !$inClass )
            {
                continue;
            }
            $code .= $text;
            if ( $text === '{' || ( is_array( $token ) && ( $token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES ) ) )
            {
                ++$depth;
            }
            else if ( $text === '}' && --$depth === 0 )
            {
                break;
            }
        }
        // a class declaration taken from a file of this checkout, never input
        eval( $code );
    }

    public static function constructorClasses()
    {
        return array(
            array( 'ezfeZPSolrQueryBuilder' ),
            array( 'eZFindResultObject' ),
            array( 'eZFindResultNode' ),
            array( 'ezfSearchResultInfo' ),
            array( 'ezfModuleFunctionCollection' ),
            array( 'ezfUpdateSearchIndexSolr' ),
        );
    }

    /** PC-01 */
    #[PHPUnit\Framework\Attributes\DataProvider( 'constructorClasses' )]
    public function testTheConstructorIsConstruct( $class )
    {
        if ( $class === 'ezfUpdateSearchIndexSolr' )
        {
            self::loadUpdateSearchIndexClass();
        }
        $reflection = new ReflectionClass( $class );
        $constructor = $reflection->getConstructor();
        $this->assertNotNull( $constructor, "$class has a constructor" );
        $this->assertSame( '__construct', $constructor->getName() );
        $this->assertSame( $class, $constructor->getDeclaringClass()->getName() );
        // the old name is kept, with the same parameters
        $this->assertTrue( $reflection->hasMethod( $class ), "$class keeps its old constructor name" );
        $this->assertSame( $constructor->getNumberOfParameters(), $reflection->getMethod( $class )->getNumberOfParameters() );
    }

    /** PC-02 */
    public function testTheQueryBuilderKeepsItsSearchEngine()
    {
        $engine = new stdClass();
        $builder = new ezfeZPSolrQueryBuilder( $engine );
        $property = new ReflectionProperty( 'ezfeZPSolrQueryBuilder', 'searchPluginInstance' );
        if ( PHP_VERSION_ID < 80100 )
        {
            $property->setAccessible( true );
        }
        $this->assertSame( $engine, $property->getValue( $builder ) );
    }

    /** PC-02: as eZFindResultNode makes it for a node of another installation */
    public function testAResultObjectHasItsLocalAttributes()
    {
        $object = new eZFindResultObject( array( 'published' => '2026-10-08T12:00:00Z', 'name' => 'not local' ) );
        $this->assertSame( '2026-10-08T12:00:00Z', $object->attribute( 'published' ) );
        $this->assertNull( $object->attribute( 'name' ) );
        $this->assertContains( 'published', $object->attributes() );
    }

    /** PC-02: as eZSolr makes it, with a row and without */
    public function testAResultNodeHasItsLocalAttributesAndItsObjectID()
    {
        $node = new eZFindResultNode( array( 'id' => 57, 'node_id' => 59, 'main_node_id' => 59, 'path_string' => '/1/2/59/' ) );
        $this->assertSame( 57, $node->ContentObjectID );
        // a node of this installation answers the attributes of a tree node from its row
        $node->setAttribute( 'is_local_installation', true );
        $this->assertSame( 59, (int)$node->attribute( 'node_id' ) );
        $this->assertSame( '/1/2/59/', $node->attribute( 'path_string' ) );
        $this->assertTrue( $node->hasAttribute( 'elevated' ) );
        $node->setAttribute( 'highlight', 'a <b>hit</b>' );
        $this->assertSame( 'a <b>hit</b>', $node->attribute( 'highlight' ) );
        $node->setAttribute( 'is_local_installation', false );
        $node->setAttribute( 'published', '2026-10-08T12:00:00Z' );
        $this->assertSame( strtotime( '2026-10-08T12:00:00Z' ), $node->attribute( 'published' ) );

        $empty = new eZFindResultNode();
        $this->assertContains( 'score_percent', $empty->attributes() );
        $this->assertNull( $empty->attribute( 'highlight' ) );
    }

    /** PC-02 */
    public function testASearchResultInfoHasItsResult()
    {
        $info = new ezfSearchResultInfo( array( 'error' => 'Solr is down', 'responseHeader' => array( 'status' => 0 ) ) );
        $this->assertTrue( $info->attribute( 'hasError' ) );
        $this->assertSame( 'Solr is down', $info->attribute( 'error' ) );
        $this->assertSame( array( 'status' => 0 ), $info->attribute( 'responseHeader' ) );
        $this->assertFalse( ( new ezfSearchResultInfo( array() ) )->attribute( 'hasError' ) );
    }

    /** PC-02 */
    public function testTheModuleFunctionCollectionIsMade()
    {
        $this->assertInstanceOf( 'ezfModuleFunctionCollection', new ezfModuleFunctionCollection() );
    }

    /** PC-02: as bin/php/updatesearchindexsolr.php makes it */
    public function testTheIndexUpdateKeepsItsScript()
    {
        self::loadUpdateSearchIndexClass();
        $script = eZScript::instance( array( 'use-session' => false, 'use-modules' => false, 'use-extensions' => false ) );
        $cli = eZCLI::instance();
        $update = new ezfUpdateSearchIndexSolr( $script, $cli, 'extension/ezfind/bin/php/updatesearchindexsolr.php' );
        $this->assertSame( $script, $update->Script );
        $this->assertSame( $cli, $update->CLI );
        $this->assertNull( $update->Options );
    }

    /** PC-03 */
    public function testNoClassHasAnOldStyleConstructorOnly()
    {
        $root = dirname( __DIR__, 2 );
        $found = array();
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $files as $file )
        {
            $path = $file->getPathname();
            if ( substr( $path, -4 ) !== '.php' || strpos( $path, '/.git/' ) !== false )
            {
                continue;
            }
            $source = file_get_contents( $path );
            if ( !preg_match_all( '/^\s*(?:abstract\s+|final\s+)?class\s+([A-Za-z0-9_]+)/m', $source, $classes ) )
            {
                continue;
            }
            foreach ( $classes[1] as $class )
            {
                if ( preg_match( '/function\s+' . $class . '\s*\(/i', $source ) &&
                     !preg_match( '/function\s+__construct\s*\(/', $source ) )
                {
                    $found[] = substr( $path, strlen( $root ) + 1 ) . ": $class";
                }
            }
        }
        $this->assertSame( array(), $found );
    }
}
