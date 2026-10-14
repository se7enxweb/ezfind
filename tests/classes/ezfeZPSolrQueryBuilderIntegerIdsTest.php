<?php
/**
 * The ids a search passes to ezfeZPSolrQueryBuilder reach the Solr filter queries as positive integers only, and
 * class attributes that do not exist or belong to another class are left out instead of stopping the search.
 * Without a database and without Solr: the class attributes come from a stand-in, the policy and language
 * filters are fixed. Runs with the PHPUnit bootstrap of an Exponential 6 kernel, from its root:
 *
 *   php vendor/bin/phpunit extension/ezfind/tests/classes/ezfeZPSolrQueryBuilderIntegerIdsTest.php
 *
 * The extension need not be active: the eZ Find classes are loaded from this checkout when the autoloads do not
 * know them.
 *
 *  ID-01 - positiveIntegerIDList(): one id or a list, cast to integers, only those above 0, no doubles
 *  ID-02 - The subtree and section filters of buildSearch() and buildMoreLikeThis() carry integers only
 *  ID-03 - Offset and limit are never below 0 and 1, and a period of SearchDate outside 1 to 5 is none
 *  ID-04 - A class attribute must exist and, with a class filter, belong to one of its classes; one that does not
 *          is no attribute filter
 *  ID-05 - A numeric class id is a positive integer of digits; anything else is looked up as a class identifier,
 *          and an entry that is no text or number is left out instead of a TypeError
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
                 'ezfeZPSolrQueryBuilder' => 'classes/ezfezpsolrquerybuilder.php' ) as $ezfindClass => $ezfindFile )
{
    if ( !class_exists( $ezfindClass ) )
    {
        require_once $ezfindRoot . '/' . $ezfindFile;
    }
}
unset( $ezfindRoot, $ezfindClass, $ezfindFile );

/** Class attributes from a list, a fixed policy filter, no language filter and no boost */
class ezfeZPSolrQueryBuilderIntegerIdsTester extends ezfeZPSolrQueryBuilder
{
    public $attributes = array();
    public $asked = array();
    public $attributeArguments = array();

    public function __construct()
    {
        $plugin = new stdClass();
        $plugin->postSearchProcessingData = array();
        parent::__construct( $plugin );
    }

    public function plugin()
    {
        return $this->searchPluginInstance;
    }

    public function validAttributes( $classAttributeIdent, $contentClassIdent )
    {
        return $this->validClassAttributeIDList( $classAttributeIdent, $contentClassIdent );
    }

    public $identifiers = array( 'folder' => 1, 'article' => 16 );

    public function classFilter( $contentClassIdent )
    {
        return $this->getContentClassFilterQuery( $contentClassIdent );
    }

    protected function fetchClassIDByIdentifier( $identifier )
    {
        return isset( $this->identifiers[$identifier] ) ? $this->identifiers[$identifier] : false;
    }

    protected function fetchClassAttribute( $classAttributeID )
    {
        $this->asked[] = $classAttributeID;
        return isset( $this->attributes[$classAttributeID] ) ? $this->attributes[$classAttributeID] : null;
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
        return array( 'attr_title_t' );
    }
}

/** A class attribute without a database */
class ezfTestIntegerIdsClassAttribute extends eZContentClassAttribute
{
    private $values;

    public function __construct( $id, $classID )
    {
        $this->values = array( 'id' => $id, 'contentclass_id' => $classID );
    }

    public function attribute( $attr, $noFunction = false )
    {
        return $this->values[$attr];
    }
}

class ezfeZPSolrQueryBuilderIntegerIdsTest extends PHPUnit\Framework\TestCase
{
    private $installationID;

    protected function setUp(): void
    {
        // the installation id is read from the database once; a fixed one keeps these tests without it
        $this->installationID = eZSolr::$InstallationID;
        eZSolr::$InstallationID = 'test';
    }

    protected function tearDown(): void
    {
        eZSolr::$InstallationID = $this->installationID;
    }

    private function query( array $params )
    {
        $builder = new ezfeZPSolrQueryBuilderIntegerIdsTester();
        return array( $builder->buildSearch( 'word', $params ), $builder );
    }

    /** ID-01 */
    public function testIdsArePositiveIntegers()
    {
        $this->assertSame( array( 2 ), ezfeZPSolrQueryBuilder::positiveIntegerIDList( '2' ) );
        $this->assertSame( array( 2, 43 ), ezfeZPSolrQueryBuilder::positiveIntegerIDList( array( '2', 43, '2', '-1', '0', 'abc', null, true, array( 5 ) ) ) );
        $this->assertSame( array( 99 ), ezfeZPSolrQueryBuilder::positiveIntegerIDList( '99 OR other' ) );
        $this->assertSame( array(), ezfeZPSolrQueryBuilder::positiveIntegerIDList( -1 ) );
        $this->assertSame( array(), ezfeZPSolrQueryBuilder::positiveIntegerIDList( false ) );
    }

    /** ID-02 */
    public function testFilterQueriesCarryIntegersOnly()
    {
        // IgnoreVisibility: the subtree filter is on meta_path_si (meta_visible_path_si without it)
        list( $query, $builder ) = $this->query( array( 'IgnoreVisibility' => true,
                                                        'SearchSectionID' => '99 OR _query_:"{!lucene}*:*"',
                                                        'SearchSubTreeArray' => array( '2', '5 OR _query_:"{!lucene}*:*"', 'x' ) ) );
        $this->assertContains( 'meta_section_id_si:99', $query['fq'] );
        $this->assertContains( 'meta_path_si:2 OR meta_path_si:5', $query['fq'] );
        $this->assertContains( 'POLICY', $query['fq'] );
        foreach ( $query['fq'] as $filter )
        {
            $this->assertStringNotContainsString( '{!', $filter );
        }
        $this->assertSame( array( 2, 5 ), $builder->plugin()->postSearchProcessingData['subtree_array'] );

        list( $query ) = $this->query( array( 'SearchSectionID' => array( '1', 'x', '3' ) ) );
        $this->assertContains( 'meta_section_id_si:1 OR meta_section_id_si:3', $query['fq'] );

        list( $query ) = $this->query( array( 'IgnoreVisibility' => true, 'SearchSectionID' => 'x', 'SearchSubTreeArray' => array( 'x' ) ) );
        foreach ( $query['fq'] as $filter )
        {
            $this->assertStringNotContainsString( 'meta_section_id_si', $filter );
            $this->assertStringNotContainsString( 'meta_path_si', $filter );
        }

        $builder = new ezfeZPSolrQueryBuilderIntegerIdsTester();
        $query = $builder->buildMoreLikeThis( 'text', 'word', array( 'SearchSectionID' => '7 OR other', 'SearchSubTreeArray' => array( '12 OR other' ) ) );
        $this->assertContains( 'meta_section_id_si:7', $query['fq'] );
        $this->assertContains( 'meta_path_si:12', $query['fq'] );
    }

    /** ID-03 */
    public function testOffsetLimitAndPeriodAreInRange()
    {
        list( $query ) = $this->query( array( 'SearchOffset' => -10, 'SearchLimit' => -5 ) );
        $this->assertSame( 0, $query['start'] );
        $this->assertSame( 10, $query['rows'] );
        list( $query ) = $this->query( array( 'SearchOffset' => '20', 'SearchLimit' => '30' ) );
        $this->assertSame( 20, $query['start'] );
        $this->assertSame( 30, $query['rows'] );
        $this->assertSame( 0, ezfeZPSolrQueryBuilder::searchOffset( array( 'SearchOffset' => array( 5 ) ) ) );
        $this->assertSame( 10, ezfeZPSolrQueryBuilder::searchLimit( array() ) );

        list( $query ) = $this->query( array( 'SearchDate' => 9 ) );
        foreach ( $query['fq'] as $filter )
        {
            $this->assertStringNotContainsString( 'meta_published_dt', $filter );
        }
    }

    /** ID-04 */
    public function testAClassAttributeMustExistAndBelongToTheClass()
    {
        $builder = new ezfeZPSolrQueryBuilderIntegerIdsTester();
        $builder->attributes = array( 10 => new ezfTestIntegerIdsClassAttribute( 10, 4 ), 11 => new ezfTestIntegerIdsClassAttribute( 11, 5 ) );
        $this->assertSame( array( 10 ), $builder->validAttributes( '10', 4 ) );
        $this->assertSame( array(), $builder->validAttributes( '11', 4 ), 'an attribute of another class' );
        $this->assertSame( array(), $builder->validAttributes( '999999', false ), 'no such attribute' );
        $this->assertSame( array( 10, 11 ), $builder->validAttributes( array( '10', '11', 'x' ), false ) );
        $this->assertSame( array( 10, 11 ), $builder->validAttributes( array( 10, 11 ), array( '4', '5' ) ) );
        $this->assertSame( array( 10, 11, 999999, 10, 11, 10, 11 ), $builder->asked, 'no lookup for what is no id' );

        // in buildSearch(): an attribute that does not exist is no attribute filter (before: a TypeError)
        $builder->buildSearch( 'word', array( 'SearchContentClassAttributeID' => '999999' ) );
        $builder->buildSearch( 'word', array( 'SearchContentClassAttributeID' => '10', 'SearchContentClassID' => 4 ) );
        $builder->buildSearch( 'word', array( 'SearchContentClassAttributeID' => '11', 'SearchContentClassID' => 4 ) );
        $this->assertSame( array( false, array( 10 ), false ), $builder->attributeArguments );
    }

    /** ID-05 */
    public function testAClassFilterTakesIntegersAndIdentifiersOnly()
    {
        $builder = new ezfeZPSolrQueryBuilderIntegerIdsTester();
        $this->assertSame( 'meta_contentclass_id_si:5', $builder->classFilter( '5' ) );
        $this->assertSame( 'meta_contentclass_id_si:5', $builder->classFilter( 5 ) );
        $this->assertSame( 'meta_contentclass_id_si:5', $builder->classFilter( ' 5 ' ) );
        $this->assertSame( 'meta_contentclass_id_si:16', $builder->classFilter( 'article' ) );
        foreach ( array( '1e0', '+1', '-1', '0', -3, 'nosuch', '5 OR other', true ) as $invalid )
        {
            $this->assertNull( $builder->classFilter( $invalid ), var_export( $invalid, true ) );
        }
        $this->assertSame( 'meta_contentclass_id_si:5 OR meta_contentclass_id_si:1',
                           $builder->classFilter( array( '1e0', '+1', '5', 'folder', array( 2 ), true, '', null ) ) );
        $this->assertNull( $builder->classFilter( array( '1e0', array( 2 ) ) ) );

        // in buildSearch(): a list inside the list is left out, not a TypeError
        $query = $builder->buildSearch( 'word', array( 'SearchContentClassID' => array( '2', array( 'x' ) ) ) );
        $this->assertContains( 'meta_contentclass_id_si:2', $query['fq'] );
    }
}
