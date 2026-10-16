<?php
/**
 * The search text a search hands to Solr: cut to the words the clause limit allows, wildcards only where
 * site.ini [SearchSettings] EnableWildcard allows them, a ? that ends a word never a wildcard; the all-words
 * filter, the class attribute of fetch( 'ezfind', 'search' ), and the message of a query Solr refuses.
 * Without a database and without Solr: the query fields come from a stand-in. Runs with the PHPUnit bootstrap of
 * an Exponential 6 kernel, from its root:
 *
 *   php vendor/bin/phpunit extension/ezfind/tests/classes/ezfQueryTextTest.php
 *
 * The extension need not be active: the eZ Find classes are loaded from this checkout when the autoloads do not
 * know them.
 *
 *  QT-01 - units(): a quoted phrase is one word, an unmatched quote belongs to the last word
 *  QT-02 - wordBudget() and limitToClauseBudget(): maxBooleanClauses / (fields + 1) words, the first ones kept
 *  QT-03 - escapeSyntax(): * and ? escaped unless EnableWildcard, a ? at the end of a word always, nothing inside
 *          a phrase, an unmatched quote dropped, an escaped character left alone
 *  QT-04 - buildSearch() cuts a long text by the number of query fields and [SearchHandler] MaxBooleanClauses,
 *          says so in SearchTextShortened, and escapes for every handler but simplestandard
 *  QT-05 - The extended attribute filter ezfindallwords requires every word (mm=100%)
 *  QT-06 - fetch( 'ezfind', 'search' ) takes class_attribute_id and hands it to the query builder
 *  QT-07 - A Solr answer without 'response' is described by its error; SearchExtras.text_shortened
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package tests
 */

$ezfindRoot = dirname( __DIR__, 2 );
foreach ( array( 'ezfSolrDocumentFieldName' => 'classes/ezfsolrdocumentfieldname.php',
                 'ezfSolrDocumentFieldBase' => 'classes/ezfsolrdocumentfieldbase.php',
                 'eZSolr' => 'search/plugins/ezsolr/ezsolr.php',
                 'eZFindElevateConfiguration' => 'classes/ezfindelevateconfiguration.php',
                 'ezfeZPSolrQueryBuilder' => 'classes/ezfezpsolrquerybuilder.php',
                 'ezfModuleFunctionCollection' => 'classes/ezfmodulefunctioncollection.php',
                 'ezfSearchResultInfo' => 'classes/ezfsearchresultinfo.php',
                 'eZFindQueryText' => 'classes/ezfindquerytext.php',
                 'eZFindExtendedAttributeFilterInterface' => 'classes/ezfindextendedattributefilterinterface.php',
                 'eZFindAllWordsExtendedAttributeFilter' => 'classes/extendedattributefilters/ezfindallwordsextendedattributefilter.php' ) as $ezfindClass => $ezfindFile )
{
    if ( !class_exists( $ezfindClass ) && !interface_exists( $ezfindClass ) )
    {
        require_once $ezfindRoot . '/' . $ezfindFile;
    }
}
unset( $ezfindRoot, $ezfindClass, $ezfindFile );

/** A given number of query fields, a fixed policy filter, no language filter and no boost */
class ezfQueryTextTester extends ezfeZPSolrQueryBuilder
{
    public $fieldCount = 1;
    public $attributeArguments = array();

    public function __construct()
    {
        $plugin = new stdClass();
        $plugin->postSearchProcessingData = array();
        parent::__construct( $plugin );
    }

    protected function policyLimitationFilterQuery( $limitation = null, $ignoreVisibility = null )
    {
        return 'POLICY';
    }

    protected function buildLanguageFilterQuery()
    {
        return false;
    }

    protected function boostQuery()
    {
        return '';
    }

    protected function getClassAttributes( $classIDArray = false, $classAttributeIDArray = false, $fieldTypeExcludeList = null )
    {
        $this->attributeArguments[] = $classAttributeIDArray;
        $fields = array();
        for ( $i = 1; $i <= $this->fieldCount; $i++ )
        {
            $fields[] = 'attr_field' . $i . '_t';
        }
        return $fields;
    }

    public function validClassAttributeIDList( $classAttributeIdent, $contentClassIdent )
    {
        return (array)$classAttributeIdent;
    }
}

class ezfQueryTextTest extends PHPUnit\Framework\TestCase
{
    private $installationID;
    private $enableWildcard;
    private $maxClauses;

    protected function setUp(): void
    {
        // the installation id is read from the database once; a fixed one keeps these tests without it
        $this->installationID = eZSolr::$InstallationID;
        eZSolr::$InstallationID = 'test';
        $site = eZINI::instance( 'site.ini' );
        $this->enableWildcard = $site->hasVariable( 'SearchSettings', 'EnableWildcard' ) ? $site->variable( 'SearchSettings', 'EnableWildcard' ) : null;
        $find = eZINI::instance( 'ezfind.ini' );
        $this->maxClauses = $find->hasVariable( 'SearchHandler', 'MaxBooleanClauses' ) ? $find->variable( 'SearchHandler', 'MaxBooleanClauses' ) : null;
    }

    protected function tearDown(): void
    {
        eZSolr::$InstallationID = $this->installationID;
        $site = eZINI::instance( 'site.ini' );
        if ( $this->enableWildcard === null && $site->hasVariable( 'SearchSettings', 'EnableWildcard' ) )
            $site->removeSetting( 'SearchSettings', 'EnableWildcard' );
        elseif ( $this->enableWildcard !== null )
            $site->setVariable( 'SearchSettings', 'EnableWildcard', $this->enableWildcard );
        $find = eZINI::instance( 'ezfind.ini' );
        if ( $this->maxClauses === null && $find->hasVariable( 'SearchHandler', 'MaxBooleanClauses' ) )
            $find->removeSetting( 'SearchHandler', 'MaxBooleanClauses' );
        elseif ( $this->maxClauses !== null )
            $find->setVariable( 'SearchHandler', 'MaxBooleanClauses', $this->maxClauses );
    }

    private function query( $text, array $params = array(), $fieldCount = 1, $wildcard = 'true', $maxClauses = '1024' )
    {
        eZINI::instance( 'site.ini' )->setVariable( 'SearchSettings', 'EnableWildcard', $wildcard );
        eZINI::instance( 'ezfind.ini' )->setVariable( 'SearchHandler', 'MaxBooleanClauses', $maxClauses );
        $builder = new ezfQueryTextTester();
        $builder->fieldCount = $fieldCount;
        return array( $builder->buildSearch( $text, $params + array( 'EnableElevation' => false ) ), $builder );
    }

    /** QT-01 */
    public function testUnits()
    {
        $this->assertSame( array(), eZFindQueryText::units( '' ) );
        $this->assertSame( array( 'one', 'two' ), eZFindQueryText::units( "  one \t two " ) );
        $this->assertSame( array( '"red apple"', 'pie' ), eZFindQueryText::units( '"red apple" pie' ) );
        $this->assertSame( array( '+"red apple"', '-pie' ), eZFindQueryText::units( '+"red apple" -pie' ) );
        $this->assertSame( array( 'one', '"two', 'three' ), eZFindQueryText::units( 'one "two three' ) );
    }

    /** QT-02 */
    public function testClauseBudget()
    {
        $this->assertSame( 12, eZFindQueryText::wordBudget( 79, 1024 ), '1024 clauses over 79 fields and one in reserve' );
        $this->assertSame( 512, eZFindQueryText::wordBudget( 1, 1024 ) );
        $this->assertSame( 512, eZFindQueryText::wordBudget( 0, 1024 ), 'no field counts as one' );
        $this->assertSame( 1, eZFindQueryText::wordBudget( 5000, 1024 ), 'at least one word' );
        $this->assertSame( 12, eZFindQueryText::wordBudget( 79, 0 ), 'no limit given: the Solr default' );

        $text = 'w1 w2 w3 w4 w5 w6 w7 w8 w9 w10 w11 w12 w13 w14 w15 w16';
        $this->assertSame( array( 'w1 w2 w3 w4 w5 w6 w7 w8 w9 w10 w11 w12', true ), eZFindQueryText::limitToClauseBudget( $text, 79, 1024 ) );
        $this->assertSame( array( $text, false ), eZFindQueryText::limitToClauseBudget( $text, 10, 1024 ) );
        $this->assertSame( array( '"a b c d" e', true ), eZFindQueryText::limitToClauseBudget( '"a b c d" e f', 400, 1024 ), 'a phrase is one word' );
        $this->assertSame( array( '  spaced  text ', false ), eZFindQueryText::limitToClauseBudget( '  spaced  text ', 1, 1024 ), 'a text within the budget is unchanged' );
    }

    /** QT-03 */
    public function testEscapeSyntax()
    {
        $this->assertSame( 'what\\?', eZFindQueryText::escapeSyntax( 'what?', true ), 'a question mark at the end is no wildcard' );
        $this->assertSame( 'why\\? because', eZFindQueryText::escapeSyntax( 'why? because', true ) );
        $this->assertSame( 'wo?d wor*', eZFindQueryText::escapeSyntax( 'wo?d wor*', true ), 'wildcards with EnableWildcard' );
        $this->assertSame( 'wo\\?d wor\\*', eZFindQueryText::escapeSyntax( 'wo?d wor*', false ), 'text without it' );
        $this->assertSame( '"what?" wor\\*', eZFindQueryText::escapeSyntax( '"what?" wor*', false ), 'nothing inside a phrase' );
        $this->assertSame( 'red apple', eZFindQueryText::escapeSyntax( 'red "apple', true ), 'an unmatched quote is dropped' );
        $this->assertSame( 'a\\*b', eZFindQueryText::escapeSyntax( 'a\\*b', false ), 'an escaped character stays as it is' );
        $this->assertSame( '+red -apple word~ AND pie', eZFindQueryText::escapeSyntax( '+red -apple word~ AND pie', false ), 'the rest of the syntax stays' );
    }

    /** QT-04 */
    public function testBuildSearchCutsAndEscapes()
    {
        $text = 'w1 w2 w3 w4 w5 w6 w7 w8 w9 w10 w11 w12 w13 w14 w15 w16';
        // 77 attribute fields plus meta_name_t and meta_owner_name_t
        list( $query, $builder ) = $this->query( $text, array(), 77 );
        $this->assertSame( 'w1 w2 w3 w4 w5 w6 w7 w8 w9 w10 w11 w12', $query['q'] );
        $this->assertTrue( $builder->SearchTextShortened );

        list( $query, $builder ) = $this->query( $text, array(), 77, 'true', '10240' );
        $this->assertSame( $text, $query['q'], 'a higher limit keeps the words' );
        $this->assertFalse( $builder->SearchTextShortened );

        list( $query ) = $this->query( 'what?' );
        $this->assertSame( 'what\\?', $query['q'] );
        list( $query ) = $this->query( 'wor*' );
        $this->assertSame( 'wor*', $query['q'], 'EnableWildcard=true keeps wildcards' );
        list( $query ) = $this->query( 'wor*', array(), 1, 'false' );
        $this->assertSame( 'wor\\*', $query['q'], 'EnableWildcard=false searches the text' );

        list( $query ) = $this->query( 'what?', array( 'QueryHandler' => 'simplestandard' ) );
        $this->assertSame( 'what?', $query['q'], 'simplestandard keeps the full syntax' );
        list( $query ) = $this->query( 'what?', array( 'QueryHandler' => 'standard', 'BoostFunctions' => array() ), 1 );
        $this->assertStringContainsString( 'attr_field1_t:(what\\?)', $query['q'] );
    }

    /** QT-05 */
    public function testAllWordsFilter()
    {
        $filter = new eZFindAllWordsExtendedAttributeFilter();
        $this->assertSame( array( 'q' => 'a b', 'mm' => '100%' ), $filter->filterQueryParams( array( 'q' => 'a b' ), array() ) );
        $this->assertSame( '100%', $filter->filterQueryParams( array( 'mm' => '1<1 2<-1' ), array() )['mm'] );

        $ini = file_get_contents( dirname( __DIR__, 2 ) . '/settings/ezfind.ini' );
        $this->assertStringContainsString( "\nFiltersList[ezfindallwords]=eZFindAllWordsExtendedAttributeFilter\n", $ini );
    }

    /** QT-06 */
    public function testFetchTakesTheClassAttribute()
    {
        $FunctionList = array();
        include dirname( __DIR__, 2 ) . '/modules/ezfind/function_definition.php';
        $names = array_column( $FunctionList['search']['parameters'], 'name' );
        $arguments = array_map( function( $parameter ) { return $parameter->getName(); },
                                ( new ReflectionMethod( 'ezfModuleFunctionCollection', 'search' ) )->getParameters() );
        $this->assertSame( count( $names ), count( $arguments ), 'one argument per fetch parameter' );
        $this->assertSame( 'class_attribute_id', end( $names ) );
        $this->assertSame( 'classAttributeID', end( $arguments ) );

        list( , $builder ) = $this->query( 'word', array( 'SearchContentClassAttributeID' => 42 ) );
        $this->assertSame( array( array( 42 ) ), $builder->attributeArguments, 'the attribute reaches the query fields' );
    }

    /** QT-07 */
    public function testRefusedQueryAndShortenedText()
    {
        $this->assertSame( 'Solr error: too many clauses', eZSolr::searchError( array( 'error' => array( 'msg' => ' too many clauses ', 'code' => 400 ) ) ) );
        $this->assertSame( 'Solr error: java.lang.NullPointerException', eZSolr::searchError( array( 'error' => array( 'trace' => "java.lang.NullPointerException\n\tat somewhere" ) ) ) );
        $this->assertSame( 'Unexpected answer from Solr (no response)', eZSolr::searchError( array( 'responseHeader' => array() ) ) );

        $info = new ezfSearchResultInfo( array( 'text_shortened' => true ) );
        $this->assertTrue( $info->hasAttribute( 'text_shortened' ) );
        $this->assertTrue( $info->attribute( 'text_shortened' ) );
        $info = new ezfSearchResultInfo( array() );
        $this->assertFalse( $info->attribute( 'text_shortened' ) );
    }
}
