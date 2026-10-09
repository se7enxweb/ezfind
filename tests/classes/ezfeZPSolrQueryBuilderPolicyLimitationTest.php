<?php
/**
 * The policy filter of ezfeZPSolrQueryBuilder with limitations that extensions define, without a database and
 * without Solr. Runs with the PHPUnit bootstrap of an Exponential 6.0.15 or later kernel, from its root:
 *
 *   php vendor/bin/phpunit extension/ezfind/tests/classes/ezfeZPSolrQueryBuilderPolicyLimitationTest.php
 *
 * The extension need not be active: the eZ Find classes are loaded from this checkout when the autoloads do not
 * know them.
 *
 *  PL-01 - Kernel limitations are translated as before (Class, Section, Subtree, Node, StateGroup_*), a kernel
 *          limitation eZ Find does not translate (Language) is left out as before, and the kernel is not asked
 *  PL-02 - An extension limitation with a search filter: the filter is ANDed with the other limitations of the
 *          policy, the policies are ORed
 *  PL-03 - An extension limitation the kernel answers with DENY_SOLR (no handler, no search filter, a handler that
 *          refuses or fails) gives its policy no access, and never widens the search, not even as the only policy
 *  PL-04 - A kernel without ezpContentLimitation::solrFilter() (or without the class): the limitation is left out,
 *          as before; and whatever is no filter from the kernel gives no access
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package tests
 */

$ezfindRoot = dirname( __DIR__, 2 );
foreach ( array( 'ezfSolrDocumentFieldName' => 'classes/ezfsolrdocumentfieldname.php',
                 'ezfSolrDocumentFieldBase' => 'classes/ezfsolrdocumentfieldbase.php',
                 'eZSolr' => 'search/plugins/ezsolr/ezsolr.php',
                 'ezfeZPSolrQueryBuilder' => 'classes/ezfezpsolrquerybuilder.php' ) as $ezfindClass => $ezfindFile )
{
    if ( !class_exists( $ezfindClass ) )
    {
        require_once $ezfindRoot . '/' . $ezfindFile;
    }
}
unset( $ezfindRoot, $ezfindClass, $ezfindFile );

/** Exposes the policy filter, and lets a test stand in another kernel class */
class ezfeZPSolrQueryBuilderPolicyLimitationTester extends ezfeZPSolrQueryBuilder
{
    public function __construct( $contentLimitationClass = 'ezpContentLimitation' )
    {
        $plugin = new stdClass();
        $plugin->postSearchProcessingData = array();
        parent::__construct( $plugin );
        $this->contentLimitationClass = $contentLimitationClass;
    }

    public function policyFilter( array $limitation, $ignoreVisibility = true )
    {
        return $this->policyLimitationFilterQuery( $limitation, $ignoreVisibility );
    }
}

/** A kernel before the search hook: it knows its own limitations, but has no solrFilter() */
class ezfTestKernelWithoutSolrFilter
{
    public static $asked = 0;

    public static function isKernelLimitation( $limitation )
    {
        ++self::$asked;
        return false;
    }
}

/** A kernel whose solrFilter() answers with something that is no filter */
class ezfTestKernelWithBrokenSolrFilter
{
    public static $answer = false;

    public static function solrFilter( $limitation, $values, $userID = false )
    {
        return self::$answer;
    }
}

/** An extension limitation with a search filter: the sections it lists */
class ezfTestSectionListLimitationHandler implements ezpContentLimitationSolrHandler
{
    public static $asked = array();
    public static $throw = false;

    public function checkAccess( $limitation, array $values, $functionName, $subject, $userID )
    {
        return true;
    }

    public function permissionSQL( $limitation, array $values, $tableAliasName, $userID )
    {
        return array( 'column' => 'ezcontentobject.section_id', 'values' => $values );
    }

    public function solrFilter( $limitation, array $values, $userID )
    {
        self::$asked[] = array( $limitation, $values );
        if ( self::$throw )
        {
            throw new RuntimeException( 'section list failed' );
        }
        return array( 'field' => 'meta_section_id_si', 'values' => $values );
    }
}

/** An extension limitation that cannot be expressed as a search filter */
class ezfTestNoSearchLimitationHandler implements ezpContentLimitationSolrHandler
{
    public function checkAccess( $limitation, array $values, $functionName, $subject, $userID )
    {
        return true;
    }

    public function permissionSQL( $limitation, array $values, $tableAliasName, $userID )
    {
        return '1 = 1';
    }

    public function solrFilter( $limitation, array $values, $userID )
    {
        return false;
    }
}

/** An extension limitation for fetches only (no ezpContentLimitationSolrHandler) */
class ezfTestSqlOnlyLimitationHandler implements ezpContentLimitationHandler
{
    public function checkAccess( $limitation, array $values, $functionName, $subject, $userID )
    {
        return true;
    }

    public function permissionSQL( $limitation, array $values, $tableAliasName, $userID )
    {
        return '1 = 1';
    }
}

class ezfeZPSolrQueryBuilderPolicyLimitationTest extends PHPUnit\Framework\TestCase
{
    const DENY = '( *:* -*:* )';

    public static function setUpBeforeClass(): void
    {
        // the filters are asked for the current user; the first look at it starts the session, which installs the
        // kernel's exception handler once per process: done here, and the handler taken off again
        if ( class_exists( 'eZUser' ) )
        {
            eZUser::currentUserID();
            $current = set_exception_handler( null );
            restore_exception_handler();
            if ( is_array( $current ) && $current[0] === 'eZExecution' )
            {
                restore_exception_handler();
            }
        }
    }

    protected function setUp(): void
    {
        if ( !class_exists( 'ezpContentLimitation' ) || !method_exists( 'ezpContentLimitation', 'solrFilter' ) )
        {
            $this->markTestSkipped( 'The kernel has no ezpContentLimitation::solrFilter() (Exponential 6.0.15 or later)' );
        }
        ezpContentLimitation::resetCache();
        ezfTestSectionListLimitationHandler::$asked = array();
        ezfTestSectionListLimitationHandler::$throw = false;
        ezfTestKernelWithoutSolrFilter::$asked = 0;
        ezfTestKernelWithBrokenSolrFilter::$answer = false;
        eZSolr::$InstallationID = 'test';
        ezpINIHelper::setINISetting( 'ezfind.ini', 'SiteSettings', 'SearchOtherInstallations', 'disabled' );
        ezpINIHelper::setINISetting( 'site.ini', 'RoleSettings', 'LimitationHandlers', array(
            'XSections' => 'ezfTestSectionListLimitationHandler',
            'XNoSearch' => 'ezfTestNoSearchLimitationHandler',
            'XSqlOnly' => 'ezfTestSqlOnlyLimitationHandler',
            // a kernel limitation never goes to a handler, even one registered for it
            'Language' => 'ezfTestSectionListLimitationHandler',
        ) );
    }

    protected function tearDown(): void
    {
        ezpINIHelper::restoreINISettings();
        ezpContentLimitation::resetCache();
        eZSolr::$InstallationID = null;
    }

    /** The filter of these policies, as policyLimitationFilterQuery() wraps it (one installation, visibility ignored) */
    private static function filter( $policies )
    {
        return '((meta_installation_id_ms:test AND (' . $policies . '))  )';
    }

    private static function limited( array $policies )
    {
        return array( 'accessWord' => 'limited', 'policies' => $policies );
    }

    /** PL-01 */
    public function testKernelLimitationsAreTranslatedAsBefore()
    {
        $qb = new ezfeZPSolrQueryBuilderPolicyLimitationTester();

        $this->assertSame(
            self::filter( '( ( meta_contentclass_id_si:2 OR meta_contentclass_id_si:16 ) AND ( meta_section_id_si:1 ))' ),
            $qb->policyFilter( self::limited( array( array( 'Class' => array( '2', '16' ), 'Section' => array( '1' ) ) ) ) )
        );
        $this->assertSame(
            self::filter( '( ( meta_section_id_si:1 )) OR ( ( meta_path_si:2 OR meta_main_node_id_si:43))' ),
            $qb->policyFilter( self::limited( array(
                array( 'Section' => array( '1' ) ),
                array( 'Subtree' => array( '/1/2/' ), 'Node' => array( '43' ) ),
            ) ) )
        );
        $this->assertSame(
            self::filter( '( ( meta_object_states_si:5 ))' ),
            $qb->policyFilter( self::limited( array( array( 'StateGroup_ez_lock' => array( '5' ) ) ) ) )
        );
        // a kernel limitation eZ Find does not translate is left out, as before; no handler is asked for it
        $this->assertSame(
            self::filter( '( ( meta_section_id_si:1 ))' ),
            $qb->policyFilter( self::limited( array( array( 'Section' => array( '1' ), 'Language' => array( 'eng-GB' ) ) ) ) )
        );
        $this->assertSame( array(), ezfTestSectionListLimitationHandler::$asked );
        // visibility, "yes" and "no" as before
        $this->assertSame(
            '((meta_installation_id_ms:test AND (( ( meta_section_id_si:1 ))))  ) AND meta_is_invisible_b:false',
            $qb->policyFilter( self::limited( array( array( 'Section' => array( '1' ) ) ) ), false )
        );
        $this->assertSame( '(meta_installation_id_ms:test)', $qb->policyFilter( array( 'accessWord' => 'yes' ) ) );
        $this->assertSame( ' NOT *:* ', $qb->policyFilter( array( 'accessWord' => 'no' ) ) );
    }

    /** PL-02 */
    public function testAnExtensionLimitationWithAFilterIsAndedWithItsPolicy()
    {
        $qb = new ezfeZPSolrQueryBuilderPolicyLimitationTester();

        $this->assertSame(
            self::filter( '( ( meta_contentclass_id_si:2 ) AND ( meta_section_id_si:(3 OR 6) ))' ),
            $qb->policyFilter( self::limited( array( array( 'Class' => array( '2' ), 'XSections' => array( '3', '6' ) ) ) ) )
        );
        $this->assertSame( array( array( 'XSections', array( '3', '6' ) ) ), ezfTestSectionListLimitationHandler::$asked );

        // the only limitation of a policy, and policies ORed
        $this->assertSame(
            self::filter( '( ( meta_section_id_si:(7) )) OR ( ( meta_contentclass_id_si:1 ))' ),
            $qb->policyFilter( self::limited( array(
                array( 'XSections' => array( '7' ) ),
                array( 'Class' => array( '1' ) ),
            ) ) )
        );

        // with locations, which stay ORed among themselves
        $this->assertSame(
            self::filter( '( ( meta_section_id_si:(3) ) AND ( meta_path_si:2 OR meta_path_si:5))' ),
            $qb->policyFilter( self::limited( array( array( 'Subtree' => array( '/1/2/', '/1/5/' ), 'XSections' => array( '3' ) ) ) ) )
        );
    }

    /** PL-03 */
    public function testAnExtensionLimitationWithoutAFilterGivesItsPolicyNoAccess()
    {
        $qb = new ezfeZPSolrQueryBuilderPolicyLimitationTester();

        foreach ( array( 'XUnregistered', 'XSqlOnly', 'XNoSearch' ) as $limitation )
        {
            // the only policy: nothing, not everything (before, the policy was dropped and the search unfiltered)
            $this->assertSame(
                self::filter( '( ' . self::DENY . ')' ),
                $qb->policyFilter( self::limited( array( array( $limitation => array( '1' ) ) ) ) ),
                $limitation
            );
            // with kernel limitations: the policy gives nothing
            $this->assertSame(
                self::filter( '( ( meta_section_id_si:1 ) AND ' . self::DENY . ')' ),
                $qb->policyFilter( self::limited( array( array( 'Section' => array( '1' ), $limitation => array( '1' ) ) ) ) ),
                $limitation
            );
            // another policy keeps its access
            $this->assertSame(
                self::filter( '( ' . self::DENY . ') OR ( ( meta_contentclass_id_si:1 ))' ),
                $qb->policyFilter( self::limited( array(
                    array( $limitation => array( '1' ) ),
                    array( 'Class' => array( '1' ) ),
                ) ) ),
                $limitation
            );
        }

        // a handler that fails
        ezfTestSectionListLimitationHandler::$throw = true;
        $this->assertSame(
            self::filter( '( ' . self::DENY . ')' ),
            $qb->policyFilter( self::limited( array( array( 'XSections' => array( '3' ) ) ) ) )
        );
    }

    /** PL-04 */
    public function testAKernelWithoutTheSearchHookKeepsTheOldBehaviour()
    {
        foreach ( array( 'ezfTestKernelWithoutSolrFilter', 'ezfTestNoSuchKernelClass' ) as $class )
        {
            $qb = new ezfeZPSolrQueryBuilderPolicyLimitationTester( $class );
            $this->assertSame(
                self::filter( '( ( meta_section_id_si:1 ))' ),
                $qb->policyFilter( self::limited( array( array( 'Section' => array( '1' ), 'XSections' => array( '3' ) ) ) ) ),
                $class
            );
        }
        $this->assertSame( 0, ezfTestKernelWithoutSolrFilter::$asked, 'isKernelLimitation() is not asked without solrFilter()' );
        $this->assertSame( array(), ezfTestSectionListLimitationHandler::$asked );

        // what is no filter gives no access
        $qb = new ezfeZPSolrQueryBuilderPolicyLimitationTester( 'ezfTestKernelWithBrokenSolrFilter' );
        foreach ( array( false, null, '', '   ', 'meta_section_id_si:3', array( 'meta_section_id_si:3' ) ) as $answer )
        {
            ezfTestKernelWithBrokenSolrFilter::$answer = $answer;
            $this->assertSame(
                self::filter( '( ( meta_section_id_si:1 ) AND ' . self::DENY . ')' ),
                $qb->policyFilter( self::limited( array( array( 'Section' => array( '1' ), 'XSections' => array( '3' ) ) ) ) ),
                var_export( $answer, true )
            );
        }
    }
}
