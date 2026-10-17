<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

class ezfeZPSolrQueryBuilder
{
    /**
     * Constructor
     *
     * Sets variables for creating a new instance of ezfeZPSolrQueryBuilder
     * @param Object $searchPluginInstance Search engine instance. Allows the query builder to
     *        communicate with the caller ( eZSolr instance ).
     */
    function __construct( $searchPluginInstance )
    {
        $this->searchPluginInstance = $searchPluginInstance;
    }

    /**
     * The constructor under its old name, which PHP 8 no longer calls on "new"; kept for code that calls it.
     *
     * @deprecated Use the constructor
     * @param Object $searchPluginInstance
     */
    function ezfeZPSolrQueryBuilder( $searchPluginInstance )
    {
        $this->__construct( $searchPluginInstance );
    }

    /**
     * @since eZ Find 2.0
     * build a multi field query, basically doing the same as a Lucene MultiField query
     * not always safe
     * @param string $searchText
     * @param array $solrFields
     * @param string $boostFields a hash array
     *
     */
    public function buildMultiFieldQuery( $searchText, $solrFields = array(), $boostFields = array() )
    {
        // simple implode implying an OR functionality
        $multiFieldQuery = '';
        // prepare boostfields arguments if any
        $processedBoostFields = array();
        foreach ( $boostFields as $baseName => $boostValue )
        {
            if ( strpos( $boostValue, ':' ) !== false && is_numeric( $baseName ) )
            {
                // split at the first colon, leave the rest intact
                list( $baseName, $boostValue ) = explode( ':', $boostValue, 2 );
            }
            if ( is_numeric( $boostValue ) )
            {
                // Get internal field name.
                $baseName = eZSolr::getFieldName( $baseName );
                $processedBoostFields[$baseName] = $boostValue;
            }
        }


        foreach ( $solrFields as $field )
        {
            //don't mind the last extra space, it's ignored by Solr
            $multiFieldQuery .= $field . ':(' . $searchText . ')';
            // check if we need to apply a boost
            if ( array_key_exists( $field, $processedBoostFields ) )
            {
                $multiFieldQuery .= '^' . $processedBoostFields[$field];
            }

            $multiFieldQuery .= ' ';

        }
        return $multiFieldQuery;
    }

    /**
     * Search on the Solr search server
     *
     * @param string search term
     * @param array parameters.
     *      Example:
     * <code>
     * array( 'SearchOffset' => <offset>,
     *        'SearchLimit' => <limit>,
     *        'SearchSubTreeArray' => array( <node ID1>[, <node ID2>]... ),
     *        'SearchContentClassID' => array( <class ID1>[, <class ID2>]... ),
     *        'SearchContentClassAttributeID' => <class attribute ID>,
     *        'Facet' => array( array( 'field' => <class identifier>/<attribute identifier>[/<option>], ... ) ) ),
     *        'Filter' => array( <base_name> => <value>, <base_name2> => <value2> ),
     *        'SortBy' => array( <field> => <asc|desc> [, <field2> => <asc|desc> [,...]] ) |
     *                    array( array( <field> => <asc|desc> )[, array( <field2> => <asc|desc> )[,...]] ),
     *        'BoostFunctions' => array( 'fields' => array(
     *                                               'article/title' => 2,
     *                                               'modified:5'
     *                                                    ),
     *                                   'functions' => array( 'rord(meta_modified_dt)^10' )
     *                                  ),
     *        'ForceElevation' => false,
     *        'EnableElevation' => true
     *        'DistributedSearch" => array ( 'shards', array( 'shard1', 'shard2' , ... )
     *                                        'searchfields', array ( 'myfield1, 'myfield2', ... )
     *                                        'returnfields', array ( 'myfield1, 'myfield2', ... )
     *                                        'rawfilterlist, array ( 'foreignfield:a', '(foreignfield:b AND otherfield:c)', ... )
     *                                      )
     *      );
     * </code>
     * For full facet description, see facets design document.
     * For full description about 'ForceElevation', see elevate support design document ( elevate_support.rst.txt )
     *
     * the rawFilterList in distributed search is appended to the policyfilterlist with an 'OR' for each entry, as the policy list will
     * in general not be applicable to foreign indexes. To be used with care!
     *
     * @param array Search types. Reserved.
     *
     * @return array Solr query results.
     *
     * @see ezfeZPSolrQueryBuilder::buildBoostFunctions()

     */
    public function buildSearch( $searchText, $params = array(), $searchTypes = array() )
    {
        eZDebugSetting::writeDebug( 'extension-ezfind-query', $params, 'search params' );
        $searchCount = 0;

        $eZFindIni = eZINI::instance( 'ezfind.ini' );
        $solrIni = eZINI::instance( 'solr.ini' );
        $siteIni = eZINI::instance( 'site.ini' );

        $offset = self::searchOffset( $params );
        $limit = self::searchLimit( $params );
        // Ids are concatenated into filter queries: only positive integers get there
        $subtrees = isset( $params['SearchSubTreeArray'] ) ? self::positiveIntegerIDList( $params['SearchSubTreeArray'] ) : array();
        $contentClassID = ( isset( $params['SearchContentClassID'] ) && $params['SearchContentClassID'] <> -1 ) ? $params['SearchContentClassID'] : false;
        $contentClassAttributeID = ( isset( $params['SearchContentClassAttributeID'] ) && $params['SearchContentClassAttributeID'] <> -1 ) ? $this->validClassAttributeIDList( $params['SearchContentClassAttributeID'], $contentClassID ) : false;
        if ( !$contentClassAttributeID )
        {
            $contentClassAttributeID = false;
        }
        $sectionID = isset( $params['SearchSectionID'] ) ? self::positiveIntegerIDList( $params['SearchSectionID'] ) : array();
        // SearchDate is one of the periods 1 (last day) to 5 (last year)
        $dateFilter = isset( $params['SearchDate'] ) && is_scalar( $params['SearchDate'] ) ? (int)$params['SearchDate'] : 0;
        if ( $dateFilter < 1 || $dateFilter > 5 )
        {
            $dateFilter = 0;
        }
        $asObjects = isset( $params['AsObjects'] ) ? $params['AsObjects'] : true;
        $spellCheck = isset( $params['SpellCheck'] ) && $params['SpellCheck'] > 0 ? $params['SpellCheck'] : array();
        $queryHandler = isset( $params['QueryHandler'] )  ?  $params['QueryHandler'] : $eZFindIni->variable( 'SearchHandler', 'DefaultSearchHandler' );
        // eZFInd 2.3: check ini setting and take it as a default instead of false
        $visibilityDefaultSetting = $siteIni->variable( 'SiteAccessSettings', 'ShowHiddenNodes' );
        $visibilityDefault = ( $visibilityDefaultSetting === 'true' ) ? true : false;
        $ignoreVisibility = isset( $params['IgnoreVisibility'] )  ?  $params['IgnoreVisibility'] : $visibilityDefault;
        $this->searchPluginInstance->postSearchProcessingData['ignore_visibility'] = $ignoreVisibility;
        $limitation = isset( $params['Limitation'] )  ?  $params['Limitation'] : null;
        $boostFunctions = isset( $params['BoostFunctions'] )  ?  $params['BoostFunctions'] : null;
        $forceElevation = isset( $params['ForceElevation'] )  ?  $params['ForceElevation'] : false;
        $enableElevation = isset( $params['EnableElevation'] )  ?  $params['EnableElevation'] : true;
        $distributedSearch = isset( $params['DistributedSearch'] ) ? $params['DistributedSearch'] : false;
        $fieldsToReturn = isset( $params['FieldsToReturn'] ) ? $params['FieldsToReturn'] : array();
        $highlightParams = isset( $params['HighLightParams'] ) ? $params['HighLightParams'] : array();
        $searchResultClusterParams = isset( $params['SearchResultClustering'] ) ? $params['SearchResultClustering'] : array();
        $extendedAttributeFilter = isset( $params['ExtendedAttributeFilter'] ) ? $params['ExtendedAttributeFilter'] : array();


        // distributed search option
        // @since ezfind 2.2
        $extraFieldsToSearch = array();
        $extraFieldsToReturn = array();
        $shardURLs = array();
        $iniShards = $solrIni->variable( 'SolrBase' , 'Shards' );
        $shardQuery = NULL;
        $shardFilterQuery = array();

        if ( isset( $distributedSearch['shards'] ) )
        {
            foreach ( $distributedSearch['shards'] as $shard )
            {
                $shardURLs[] = $iniShards[$shard];
            }
            $shardQuery = implode( ',', $shardURLs );
        }
        if ( isset( $distributedSearch['searchfields'] ) )
        {
            $extraFieldsToSearch = $distributedSearch['searchfields'];

        }
        if ( isset( $distributedSearch['returnfields'] ) )
        {
            $extraFieldsToReturn = $distributedSearch['returnfields'];

        }
        if ( isset( $distributedSearch['rawfilterlist'] ) )
        {
            $shardFilterQuery = $distributedSearch['rawfilterlist'];

        }

        // check if filter parameter is indeed an array, and set it otherwise
        if ( isset( $params['Filter']) && ! is_array( $params['Filter'] ) )
        {
            $params['Filter'] = array( $params['Filter'] );
        }

        $pathFieldName = eZSolr::getMetaFieldName( $ignoreVisibility ? 'path' : 'visible_path' );


        $filterQuery = array();

        // Add subtree query filter
        if ( !empty( $subtrees ) )
        {
            $this->searchPluginInstance->postSearchProcessingData['subtree_array'] = $subtrees;
            $subtreeQueryParts = array();
            foreach ( $subtrees as $subtreeNodeID )
            {
                $subtreeQueryParts[] = $pathFieldName . ':' . $subtreeNodeID;
            }

            $filterQuery[] = implode( ' OR ', $subtreeQueryParts );
        }

        // Add policy limitation query filter
        $policyLimitationFilterQuery = $this->policyLimitationFilterQuery( $limitation, $ignoreVisibility );
        if ( $policyLimitationFilterQuery !== false )
        {
            $filterQuery[] = $policyLimitationFilterQuery;
        }

		// Add time/date query filter
    	if ( $dateFilter > 0 )
		{
    		switch ( $dateFilter )
			{
				// last day
				case 1:
					$searchTimestamp = strtotime( '-1 day' );
				break;
				// last week
				case 2:
					$searchTimestamp = strtotime( '-1 week' );
					break;
				// last month
				case 3:
					$searchTimestamp = strtotime( '-1 month' );
					break;
				// last three month
				case 4:
					$searchTimestamp = strtotime( '-3 month' );
					break;
				// last year
				case 5:
					$searchTimestamp = strtotime( '-1 year' );
				break;
			}
			$filterQuery[] = eZSolr::getMetaFieldName( 'published' ) . ':[' . ezfSolrDocumentFieldBase::preProcessValue( $searchTimestamp, 'date' ) .'/DAY TO *]';
		}

        if ( (!eZContentObjectTreeNode::showInvisibleNodes() || !$ignoreVisibility ) && ( $eZFindIni->variable( 'SearchFilters', 'FilterHiddenFromDB' ) == 'enabled' ) )
        {
            $db = eZDB::instance();
            $invisibleNodeIDArray = $db->arrayQuery( 'SELECT node_id FROM ezcontentobject_tree WHERE ezcontentobject_tree.is_invisible = 1', array( 'column' => 0) );
            $hiddenNodesQueryText = 'meta_main_node_id_si:[* TO *] -meta_main_node_id_si:(';
            foreach ( $invisibleNodeIDArray as $element )
            {
                $hiddenNodesQueryText =  $hiddenNodesQueryText . $element['node_id'] . ' ';
            }
            $hiddenNodesQueryText = $hiddenNodesQueryText . ')';
            // only add filter if there are hidden nodes after all
            if ( $invisibleNodeIDArray )
            {
                $filterQuery[] = $hiddenNodesQueryText;
            }

        }

        // Add content class query filter
        $classLimitationFilter = $this->getContentClassFilterQuery( $contentClassID );
        if ( $classLimitationFilter !== null )
        {
            $filterQuery[] = $classLimitationFilter;
        }

        // Add section to query filter.
        $sectionLimitationFilter = $this->getSectionFilterQuery( $sectionID );
        if ( $sectionLimitationFilter !== null )
        {
            $filterQuery[] = $sectionLimitationFilter;
        }

        $languageFilterQuery = $this->buildLanguageFilterQuery();
        if ( $languageFilterQuery )
        {
            $filterQuery[] = $languageFilterQuery;
        }


        $paramFilterQuery = $this->getParamFilterQuery( $params );
        if ( $paramFilterQuery )
        {
            $filterQuery[] = $paramFilterQuery;
        }

        //add raw filters
        if ( $eZFindIni->hasVariable( 'SearchFilters', 'RawFilterList' ) )
        {
            $rawFilters = $eZFindIni->variable( 'SearchFilters', 'RawFilterList' );
            if ( is_array( $rawFilters ) )
            {
                $filterQuery = array_merge( $filterQuery, $rawFilters );
            }
        }

        // Build and get facet query prameters.
        $facetQueryParamList = $this->buildFacetQueryParamList( $params );

        // search only text type declared fields
        $fieldTypeExcludeList = $this->fieldTypeExludeList( NULL );

        // Create sort parameters based on the parameters.
        $sortParameter = $this->buildSortParameter( $params );

        //the array_unique below is necessary because attribute identifiers are not unique .. and we get as
        //much highlight snippets as there are duplicate attribute identifiers
        //these are also in the list of query fields (dismax, ezpublish) request handlers
	$queryFields = array_unique( $this->getClassAttributes( $contentClassID, $contentClassAttributeID, $fieldTypeExcludeList ) );

        //highlighting only in the attributes, otherwise the object name is repeated in the highlight, which is already
        //partly true as it is mostly composed of one or more attributes.
        //maybe we should add meta data to the index to filter them out.

        $highLightFields = $queryFields;

        //@since eZ Find 2.3
        //when dedicated attributes are searched for, don't add meta-fields to the $queryfields list
        if ( !$contentClassAttributeID )
        {
            $queryFields[] = eZSolr::getMetaFieldName( 'name' );
            if ( !$eZFindIni->hasVariable( 'SearchFilters', 'ExcludeOwnerName' ) || $eZFindIni->variable( 'SearchFilters', 'ExcludeOwnerName' ) !== 'enabled' )
            {
                $queryFields[] = eZSolr::getMetaFieldName( 'owner_name' );
            }
        }


        $spellCheckParamList = array();
        // @param $spellCheck expects array (true|false, dictionary identifier, ...)
        if ( ( isset( $spellCheck[0] ) and $spellCheck[0] ) or
             ( $eZFindIni->variable( 'SpellCheck', 'SpellCheck' ) == 'enabled' and ( isset( $spellCheck[0] ) and !$spellCheck[0] ) ) )
        {
            $dictionary = isset( $spellCheck[1]) ? $spellCheck[1] : $eZFindIni->variable( 'SpellCheck', 'DefaultDictionary' );
            $spellCheckParamList = array(
                'spellcheck' => 'true',
                // q is manipulated in case of standard request handler, so make it explicit by using spellcheck.q
                'spellcheck.q' => $searchText,
                'spellcheck.dictionary' => $dictionary,
                'spellcheck.collate' => 'true',
                'spellcheck.extendedResults' => 'true',
                'spellcheck.onlyMorePopular' => 'true',
                'spellcheck.count' => 1);
        }

        // Create the Elevate-related parameters here :
        $elevateParamList = eZFindElevateConfiguration::getRuntimeQueryParameters( $forceElevation, $enableElevation, $searchText );

        // process query handler: standard, simplestandard, ezpublish, heuristic
        // first determine which implemented handler to use when heuristic is specified
        if ( strtolower( $queryHandler ) === 'heuristic' )
        {
            // @todo: this code will evolve of course
            if ( preg_match( '/[\^\*\~]|AND|OR/', $searchText) > 0 )
            {
                $queryHandler = 'simplestandard';
            }
            else
            {
                $queryHandler = 'ezpublish';
            }
        }

        $handlerParameters = array();

        $queryHandler = strtolower( $queryHandler );

        // Every word is one clause per query field, and Solr refuses a query above maxBooleanClauses ("too many
        // nested clauses"; measured with 79 query fields: 15 and 16 words failed, while site.ini
        // [SearchSettings] MaximumSearchTextWords allowed 16). The words are cut to what the fields allow:
        // simplestandard searches the one aggregated field, every other handler all query fields.
        $maxClauses = $eZFindIni->hasVariable( 'SearchHandler', 'MaxBooleanClauses' ) ? (int)$eZFindIni->variable( 'SearchHandler', 'MaxBooleanClauses' ) : 0;
        $budgetFieldCount = $queryHandler === 'simplestandard' ? 1 : count( $queryFields ) + count( $extraFieldsToSearch );
        list( $searchText, $this->SearchTextShortened ) = eZFindQueryText::limitToClauseBudget(
            $searchText, $budgetFieldCount, $maxClauses > 0 ? $maxClauses : eZFindQueryText::DEFAULT_MAX_CLAUSES );
        if ( $this->SearchTextShortened )
        {
            eZDebug::writeNotice( 'The search text was cut to ' . count( eZFindQueryText::units( $searchText ) ) . ' words for ' . $budgetFieldCount . ' query fields', __METHOD__ );
        }

        // Solr reads * and ? as wildcards whatever site.ini [SearchSettings] EnableWildcard says, and "what?"
        // searched for a single-character wildcard and found nothing. Escaped here, except for simplestandard,
        // which is chosen for the full query syntax.
        if ( $queryHandler !== 'simplestandard' )
        {
            $searchText = eZFindQueryText::escapeSyntax( $searchText, $siteIni->variable( 'SearchSettings', 'EnableWildcard' ) === 'true' );
        }

        switch ( $queryHandler )
        {
            case 'standard':
                // @todo: this is more complicated
                // build the query against all "text" like fields
                // should take into account all the filter fields and class filters to shorten the query
                // need to build: Solr q
                if ( array_key_exists( 'fields', $boostFunctions ) )
                {

                    $handlerParameters = array ( 'q' => $this->buildMultiFieldQuery( $searchText, array_merge( $queryFields, $extraFieldsToSearch ), $boostFunctions['fields'] ),
                                             'qt' => 'standard' );
                }
                else
                {
                    $handlerParameters = array ( 'q' => $this->buildMultiFieldQuery( $searchText, array_merge( $queryFields, $extraFieldsToSearch ) ),
                                             'qt' => 'standard' );
                }
                break;

            case 'simplestandard':
                // not to do much, searching is against the default aggregated field
                // only highlightfields
                $highLightFields = array ( 'ezf_df_text' );
                $handlerParameters = array ( 'q' => $searchText,
                                             'qt' => 'standard',
                                             'hl.usePhraseHighlighter' => 'true',
                                             'hl.highlightMultiTerm' => 'true' );
                break;
            case 'ezpublish':
                // the dismax based handler, just keywordss input, most useful for ordinary queries by users
                // need to build: Solr q, qf, dismax specific parameters

            default:
                // ezpublish of course, this to not break BC and is the most "general"
                // if another value is specified, it is supposed to be a dismax like handler
                // with possible other tuning variables then the stock provided 'ezpublish' in solrconfi.xml
                // remark it should be lowercase in solrconfig.xml!

                $boostQueryString = $this->boostQuery();
                $rawBoostQueries = $eZFindIni->variable( 'QueryBoost', 'RawBoostQueries' );
                if ( is_array( $rawBoostQueries ) && !empty( $rawBoostQueries ) )
                {
                    $boostQueryString .= ' ' . implode( ' ', $rawBoostQueries );
                }
                $handlerParameters = array ( 'q'  => $searchText,
                                             'bq' => $boostQueryString,
                                             'qf' => implode( ' ', array_merge( $queryFields, $extraFieldsToSearch ) ),
                                             'qt' => $queryHandler );

        }

        // Handle boost functions :
        $boostFunctionsParamList = $this->buildBoostFunctions( $boostFunctions, $handlerParameters );

        // special handling of filters in the case of distributed search filters
        // incorporate distributed search filters if defined with an OR expression, and AND-ing all others
        // need to do this as multiple fq elements are otherwise AND-ed by the Solr backend
        // when using this to search across a dedicated set of languages, it will still be valid with the ezp permission
        // scheme
        if ( !empty( $shardFilterQuery ) )
        {
            $fqString = '((' . implode( ') AND (', $filterQuery ) . ')) OR ((' . implode( ') OR (', $shardFilterQuery ) . '))';
            // modify the filterQuery array with this single string as the only element
            $filterQuery = array( $fqString );
        }

        // Document transformer fields since eZ Find 5.4
        $docTransformerFields = array( '[elevated]' );

        $fieldsToReturnString = eZSolr::getMetaFieldName( 'guid' ) . ' ' . eZSolr::getMetaFieldName( 'installation_id' ) . ' ' .
                eZSolr::getMetaFieldName( 'main_url_alias' ) . ' ' . eZSolr::getMetaFieldName( 'installation_url' ) . ' ' .
                eZSolr::getMetaFieldName( 'id' ) . ' ' . eZSolr::getMetaFieldName( 'main_node_id' ) . ' ' .
                eZSolr::getMetaFieldName( 'language_code' ) . ' ' . eZSolr::getMetaFieldName( 'name' ) .
                ' score ' . eZSolr::getMetaFieldName( 'published' ) . ' ' . eZSolr::getMetaFieldName( 'path_string' ) . ' ' .
                eZSolr::getMetaFieldName( 'main_path_string' ) . ' ' . eZSolr::getMetaFieldName( 'is_invisible' ) . ' ' .
                implode( ' ', $docTransformerFields) . ' ' .
                implode( ' ', $extraFieldsToReturn );

        if ( ! $asObjects )
        {
            if ( empty( $fieldsToReturn ))
            {
                // @todo: needs to be refined with Solr supporting globbing in fl argument, otherwise requests will be to heavy for large fields as for example binary file content
                $fieldsToReturnString = 'score, *';
            }
            else
            {
                $fieldsToReturnString .= ' ' . implode( ' ', $fieldsToReturn);
            }

        }

        $searchResultClusterParamList = array( 'clustering' => 'true');
        $searchResultClusterParamList = $this->buildSearchResultClusterQuery($searchResultClusterParams);
        eZDebugSetting::writeDebug( 'extension-ezfind-query', $searchResultClusterParamList, 'Cluster params' );


        $queryParams =  array_merge(
            $handlerParameters,
            array(
                'start' => $offset,
                'rows' => $limit,
                'sort' => $sortParameter,
                'indent' => 'on',
                'version' => '2.2',
                'fl' => $fieldsToReturnString,
                'fq' => $filterQuery,
                'hl' => $eZFindIni->variable( 'HighLighting', 'Enabled' ),
                'hl.fl' => implode( ' ', $highLightFields ),
                'hl.snippets' => $eZFindIni->variable( 'HighLighting', 'SnippetsPerField' ),
                'hl.fragsize' => $eZFindIni->variable( 'HighLighting', 'FragmentSize' ),
                'hl.requireFieldMatch' => $eZFindIni->variable( 'HighLighting', 'RequireFieldMatch' ),
                'hl.simple.pre' => $eZFindIni->variable( 'HighLighting', 'SimplePre' ),
                'hl.simple.post' => $eZFindIni->variable( 'HighLighting', 'SimplePost' ),
                'wt' => 'php'
            ),
            $facetQueryParamList,
            $spellCheckParamList,
            $boostFunctionsParamList,
            $elevateParamList,
            $searchResultClusterParamList
        );


        if( isset( $extendedAttributeFilter['id'] ) && isset( $extendedAttributeFilter['params'] ) )
        {
            //single filter
            $extendedAttributeFilter = array( $extendedAttributeFilter );
        }

        foreach( $extendedAttributeFilter as $filterDefinition )
        {
            if( isset( $filterDefinition['id'] ) )
            {
                $filter = eZFindExtendedAttributeFilterFactory::getInstance( $filterDefinition['id'] );
                if( $filter )
                {
                    $filterParams = isset( $filterDefinition['params'] ) ? $filterDefinition['params'] : array();
                    $queryParams = $filter->filterQueryParams( $queryParams, $filterParams );
                }
            }
        }

        return $queryParams;
    }

    /**
     * @since eZ Find 2.1
     *
     * Language filtering.
     * This method builds the language filter, depending on the following settings :
     *
     * In site.ini :
     * <code>
     * # Prioritized list of languages. Only translations in these
     * # languages will be shown
     *
     * [RegionalSettings]
     * SiteLanguageList[]
     * SiteLanguageList[]=eng-GB
     * SiteLanguageList[]=fre-FR
     * </code>
     *
     * And in ezfind.ini :
     * <code>
     * [LanguageSearch]
     * SearchMainLanguageOnly=enabled
     * </code>
     *
     * When SearchMainLanguageOnly is set to 'enabled', only results in the first language in SiteLanguageList[] will be returned.
     * When SearchMainLanguageOnly is set to 'disabled', searching will be done across all possible translations defined in
     * SiteLanguageList[] (unless ShowUntranslatedObjects is enabled, in this case no language filtering will be done at all)
     *
     *
     * @return string The correct language filtering string, appended to the 'fq' parameter in the Solr request.
     */
    protected function buildLanguageFilterQuery()
    {
        $languageFilterString = '';
        $eZFindIni = eZINI::instance( 'ezfind.ini' );
        $ini = eZINI::instance();
        $languages = $ini->variable( 'RegionalSettings', 'SiteLanguageList' );
        $searchMainLanguageOnly = $eZFindIni->variable( 'LanguageSearch', 'SearchMainLanguageOnly' ) == 'enabled';
        $showUntranslatedObjects = $ini->variable( 'RegionalSettings', 'ShowUntranslatedObjects' ) == 'enabled';
        $languageCodeMetaName = eZSolr::getMetaFieldName( 'language_code' );
        $availableLanguageCodesMetaName = eZSolr::getMetaFieldName( 'available_language_codes' );

        if (  $searchMainLanguageOnly )
        {
            $languageFilterString = $languageCodeMetaName . ':' . $languages[0];
        }
        else if ( $showUntranslatedObjects === false )
        {
            $languageFilterString = $languageCodeMetaName . ':(' . implode( ' OR ' , $languages ) . ')';
            $languageFilterString .= " OR ( " . eZSolr::getMetaFieldName( 'always_available' ) . ':true )';
        }

        return $languageFilterString;
    }

    /**
     * @since eZ Find 2.0
     *
     * Boost Functions support.
     * "Allows one to use the actual value of a numeric field and functions of those fields in a relevancy score."
     *
     * The fields and functions are passed to Solr as they are given (BoostFunctions, like QueryBoost
     * RawBoostQueries of ezfind.ini). They must come from code or settings, never from request input.
     *
     * @see http://wiki.apache.org/solr/FunctionQuery
     * @param array $boostFunctions Example :
     * <code>
     * $boostFunctions = array( 'fields' => array(
     *                                             'article/title' => 2,
     *                                             'modified:5'
     *                                            ),
     *                          'functions' => array( 'rord(meta_modified_dt)^10' )
     *                        );
     * </code>
     * @param array &$handlerParameters The inclusion of boost functions in the final search parameter array depends on which queryHandler is used.
     *                                  This parameter shall be modified in one of the cases.
     *
     * @return array containing the boost expressions for the various request handler boost parameters
     */
    protected function buildBoostFunctions( $boostFunctions, &$handlerParameters )
    {
        if ( $boostFunctions == null )
            return array();

        // Build boost function string here.
        //   Field boosts and functions seems to be mutually exclusive.
        $boostString = '';
        $processedBoostFunctions = array();
        $processedBoostFunctions['fields'] = $processedBoostFunctions['functions'] = array();

        // Process simple query-time field boosting first :
        if ( array_key_exists(  'fields', $boostFunctions ) )
        {
            foreach ( $boostFunctions['fields'] as $baseName => $boostValue )
            {
                    if ( strpos( $boostValue, ':' ) !== false && is_numeric( $baseName ) )
                    {
                        // split at the first colon, leave the rest intact
                        list( $baseName, $boostValue ) = explode( ':', $boostValue, 2 );
                    }
                    if ( is_numeric( $boostValue ) )
                    {
                        // Get internal field name.
                        $baseName = eZSolr::getFieldName( $baseName );
                        $processedBoostFunctions['fields'][] = $baseName . '^' . $boostValue;
                    }
            }
        }

        if ( array_key_exists(  'functions', $boostFunctions ) )
        {
            // Process simple query-time field boosting first :
            foreach ( $boostFunctions['functions'] as $expression )
            {
                // @TODO : parse $expression. use an ezi18n-like system ( formats ), meaning that the $boostFunctions['functions'] will look like this :
                /* <code>
                 * array( 'product( pow( %rating, 5 ), %modified )' => array( '%rating'    => 'article/rating',
                 *                                                            '%modified'  => 'modified' )
                 *      );
                 * </code>
                 *
                 * Eventually, one single expression is to be accepted here, as is the case in Solr.
                 */
                $processedBoostFunctions['functions'][] = $expression;
            }
        }
        switch ( $handlerParameters['qt'] )
        {
        	case 'ezpublish' :
        	{
        	// The edismax based handler which takes its own boost parameters
                // Push the boost expression in the 'bf' parameter, if it is not empty.
                //
                // for the fields to boost, modify the qf parameter for edismax
                // this is set before in the buildSearch method
                $queryFields = explode(' ', $handlerParameters['qf']);
                foreach ( $processedBoostFunctions['fields'] as $fieldToBoost => $boostString )
                {
                    $key = array_search($fieldToBoost, $queryFields);
                    if (false !== $key)
                    {
                        $queryFields[$key] = $boostString;
                    }
                    // might be a custom created field, lets add it implicitely with its boost specification
                    else
                    {
                        $queryFields[] = $boostString;
                    }
                }
                $handlerParameters['qf'] = implode( ' ', $queryFields );

                $boostReturnArray = array();

                //additive boost functions
                if ( array_key_exists(  'functions', $boostFunctions ) )
                {
                    $boostReturnArray['bf'] = $boostFunctions['functions'];
                }

                // multiplicative boost functions
                if ( array_key_exists(  'mfunctions', $boostFunctions ) )
                {
                    $boostReturnArray['boost'] = $boostFunctions['mfunctions'];
                }

                //add the queries to the existing bq edismax parameter
                if ( array_key_exists(  'queries', $boostFunctions ) )
                {
                    $handlerParameters['bq'] .= ' ' . implode(' ', $boostFunctions['queries']);
                }

                return $boostReturnArray;
        	} break;

        	default:
        	{
        	    // Simplestandard or standard search handlers.
        	    // Append the boost expression to the 'q' parameter.
        	    // Alter the $handlerParameters array ( passed as reference )
        	    // @TODO : Handle query-time field boosting through the buildMultiFieldQuery() method.
        	    //         Requires a modified 'heuristic' mode.
        	    $boostString = implode( ' ', $processedBoostFunctions['functions'] );
                $handlerParameters['q'] .= ' _val_:' . trim( $boostString );
        	} break;
        }
        return array();
    }

    /**
     * @since eZ Find 2.0
     *
     * More Like This similarity searches
     * @param query
     *
     * @return
     */
    public function buildMoreLikeThis( $queryType, $query, $params = array() )
    {
        eZDebugSetting::writeDebug( 'extension-ezfind-query-mlt', $queryType, 'mlt querytype' );
        eZDebugSetting::writeDebug( 'extension-ezfind-query-mlt', $query, 'mlt query' );
        eZDebugSetting::writeDebug( 'extension-ezfind-query-mlt', $params, 'mlt params' );
        $searchCount = 0;

        $eZFindIni = eZINI::instance( 'ezfind.ini' );

        $queryInstallationID = ( isset( $params['QueryInstallationID'] ) && $params['QueryInstallationID'] ) ? $params['QueryInstallationID'] : eZSolr::installationID();
        $offset = self::searchOffset( $params );
        $limit = self::searchLimit( $params );
        $subtrees = isset( $params['SearchSubTreeArray'] ) ? self::positiveIntegerIDList( $params['SearchSubTreeArray'] ) : array();
        $contentClassID = ( isset( $params['SearchContentClassID'] ) && $params['SearchContentClassID'] <> -1 ) ? $params['SearchContentClassID'] : false;
        $sectionID = isset( $params['SearchSectionID'] ) ? self::positiveIntegerIDList( $params['SearchSectionID'] ) : array();
        $filterQuery = array();


        // Add subtree query filter
        if ( !empty( $subtrees ) )
        {
            $subtreeQueryParts = array();
            foreach ( $subtrees as $subtreeNodeID )
            {
                $subtreeQueryParts[] = eZSolr::getMetaFieldName( 'path' ) . ':' . $subtreeNodeID;
            }

            $filterQuery[] = implode( ' OR ', $subtreeQueryParts );
        }

        // Add policy limitation query filter
        $policyLimitationFilterQuery = $this->policyLimitationFilterQuery();
        if ( $policyLimitationFilterQuery !== false )
        {
            $filterQuery[] = $policyLimitationFilterQuery;
        }

        // Add content class query filter
        $classLimitationFilter = $this->getContentClassFilterQuery( $contentClassID );
        if ( $classLimitationFilter !== null )
        {
            $filterQuery[] = $classLimitationFilter;
        }

        // Add section to query filter.
        $sectionLimitationFilter = $this->getSectionFilterQuery( $sectionID );
        if ( $sectionLimitationFilter !== null )
        {
            $filterQuery[] = $sectionLimitationFilter;
        }

        $languageFilterQuery = $this->buildLanguageFilterQuery();
        if ( $languageFilterQuery )
        {
            $filterQuery[] = $languageFilterQuery;
        }

        $paramFilterQuery = $this->getParamFilterQuery( $params );
        if ( $paramFilterQuery )
        {
            $filterQuery[] = $paramFilterQuery;
        }

        //add raw filters
        if ( $eZFindIni->hasVariable( 'SearchFilters', 'RawFilterList' ) )
        {
            $rawFilters = $eZFindIni->variable( 'SearchFilters', 'RawFilterList' );
            if ( is_array( $rawFilters ) )
            {
                $filterQuery = array_merge( $filterQuery, $rawFilters );
            }
        }

        // Build and get facet query prameters.
        $facetQueryParamList = $this->buildFacetQueryParamList( $params );

        // return only text searcheable fields by passing NULL
        $fieldTypeExcludeList = $this->fieldTypeExludeList( NULL );

        // Create sort parameters based on the parameters.
        $sortParameter = $this->buildSortParameter( $params );
        $iniExtractionFields = $eZFindIni->variable( 'MoreLikeThis', 'ExtractionFields' );

        if ( $iniExtractionFields == 'general' )
        {
            // the collector field for all strings in an object
            $queryFields = array( 'ezf_df_text' );
        }
        else
        {
            //the array_unique below is necessary because attribute identifiers are not unique .. and we get as
            //much highlight snippets as there are duplicate attribute identifiers
            //these are also in the list of query fields (dismax, ezpublish) request handlers
            $queryFields = array_unique( $this->getClassAttributes( $contentClassID, false, $fieldTypeExcludeList ) );
        }

        //query type can vary for MLT q, or stream
        //if no valid match for the mlt query variant is obtained, it is treated as text
        $mltVariant = 'q';
        switch ( strtolower( $queryType ) )
        {
            case 'nid':
                $mltQuery = eZSolr::getMetaFieldName( 'node_id' ) . ':' . self::moreLikeThisID( $query );
                $mltQuery .= ' AND ' . eZSolr::getMetaFieldName( 'installation_id' ) . ':' . $queryInstallationID;
                break;
            case 'oid':
                $mltQuery = eZSolr::getMetaFieldName( 'id' ) . ':' . self::moreLikeThisID( $query );
                $mltQuery .= ' AND ' . eZSolr::getMetaFieldName( 'installation_id' ) . ':' . $queryInstallationID;
                break;
            // url: Solr fetches the document at this address (stream.url), so it must come from code or
            // settings, never from request input
            case 'url':
                $mltVariant = 'stream.url';
                $mltQuery = $query;
                break;
            case 'text':
            default:
                $mltVariant = 'stream.body';
                $mltQuery = $query;
                break;
        }

        // fetch the mlt tuning parameters from ini settings

        $mintf = $eZFindIni->hasVariable( 'MoreLikeThis', 'MinTermFreq' )  ? $eZFindIni->variable( 'MoreLikeThis', 'MinTermFreq' ) : 1;
        $mindf = $eZFindIni->hasVariable( 'MoreLikeThis', 'MinDocFreq' ) ? $eZFindIni->variable( 'MoreLikeThis', 'MinDocFreq' ) : 1;
        $minwl = $eZFindIni->hasVariable( 'MoreLikeThis', 'MinWordLength' ) ? $eZFindIni->variable( 'MoreLikeThis', 'MinWordLength' ) : 3;
        $maxwl = $eZFindIni->hasVariable( 'MoreLikeThis', 'MaxWordLength' ) ? $eZFindIni->variable( 'MoreLikeThis', 'MaxWordLength' ) : 20;
        $maxqt = $eZFindIni->hasVariable( 'MoreLikeThis', 'MaxQueryTerms' ) ? $eZFindIni->variable( 'MoreLikeThis', 'MaxQueryTerms' ) : 5;
        $boostmlt = $eZFindIni->hasVariable( 'MoreLikeThis', 'BoostTerms' ) ? $eZFindIni->variable( 'MoreLikeThis', 'BoostTerms' ) : 'true';

        // @todo decide which of the hard-coded mlt parameters should become input parameters or ini settings
        return array_merge(
            array(
                $mltVariant => $mltQuery,
                'start' => $offset,
                'rows' => $limit,
                'sort' => $sortParameter,
                'indent' => 'on',
                'version' => '2.2',
                'mlt.match.include' => 'false', // exclude the doc itself
                'mlt.mindf' => $mindf,
                'mlt.mintf' => $mintf,
                'mlt.maxwl' => $maxwl,
                'mlt.minwl' => $minwl, //minimum wordlength
                'mlt.maxqt' => $maxqt,
                'mlt.interestingTerms' => 'details', // useful for debug output & tuning
                'mlt.boost' => $boostmlt, // boost the highest ranking terms
                //'mlt.qf' => implode( ' ', $queryFields ),
                'mlt.fl' => implode( ' ', $queryFields ),
                'fl' =>
                eZSolr::getMetaFieldName( 'guid' ) . ' ' . eZSolr::getMetaFieldName( 'installation_id' ) . ' ' .
                eZSolr::getMetaFieldName( 'main_url_alias' ) . ' ' . eZSolr::getMetaFieldName( 'installation_url' ) . ' ' .
                eZSolr::getMetaFieldName( 'id' ) . ' ' . eZSolr::getMetaFieldName( 'main_node_id' ) . ' ' .
                eZSolr::getMetaFieldName( 'language_code' ) . ' ' . eZSolr::getMetaFieldName( 'name' ) .
                ' score ' . eZSolr::getMetaFieldName( 'published' ) . ' ' .
                eZSolr::getMetaFieldName( 'path_string' ) . ' ' . eZSolr::getMetaFieldName( 'is_invisible' ),
                'fq' => $filterQuery,
                'wt' => 'php' ),
            $facetQueryParamList );

        return $queryParams;
    }

    /**
     * Build sort parameter based on params provided.
     * @todo specify dedicated sorting fields
     * @param array Parameter list array. SortBy element contains sort
     * definition.
     *
     * @return string Sort description. Default sort string is 'score desc'.
     */
    protected function buildSortParameter( $parameterList )
    {
        $sortString = 'score desc';

        if ( !empty( $parameterList['SortBy'] ) )
        {
            $sortString = '';
            foreach ( $parameterList['SortBy'] as $field => $order )
            {
                // If array, set key and order from array values
                if ( is_array( $order ) )
                {
                    $field = $order[0];
                    $order = $order[1];
                }

                // Fixup field name
                switch( $field )
                {
                    case 'score':
                    case 'relevance':
                    {
                        $field = 'score';
                    } break;

                    case 'name':
                    {
                        $field = eZSolr::getMetaFieldName( 'sort_name', 'sort' );
                    }break;

                    case 'published':
                    case 'modified':
                    case 'class_name':
                    case 'class_identifier':
                    case 'section_id':
                    {
                        $field = eZSolr::getMetaFieldName( $field, 'sort' );
                    } break;

                    case 'author':
                    {
                        $field = eZSolr::getMetaFieldName( 'owner_name', 'sort' );
                    } break;

                    case 'class_id':
                    {
                        $field = eZSolr::getMetaFieldName( 'contentclass_id', 'sort' );
                    } break;

                    case 'path':
                    {
                        // Assume sorting on main node path_string as it is not possible to sort on multivalued fields due to Solr limitation
                        $field = eZSolr::getMetaFieldName( 'main_path_string', 'sort' );
                    } break;

                    default:
                    {
                        $baseFieldName = $field;
                        $field = eZSolr::getFieldName( $field, false, 'sort' );
                        if ( !$field )
                        {
                            eZDebug::writeNotice( 'Sort field does not exist in local installation, but may still be valid: ' .
                                                  $baseFieldName,
                                                  __METHOD__ );
                            continue 2;
                        }
                    } break;
                }

                // Fixup order name.
                switch( strtolower( $order ) )
                {
                    case 'desc':
                    case 'asc':
                    {
                        $order = strtolower( $order );
                    } break;

                    default:
                    {
                        eZDebug::writeDebug( 'Unrecognized sort order. Setting for order for default: "desc"',
                                             __METHOD__ );
                        $order = 'desc';
                    } break;
                }

                if ( $sortString !== '' )
                {
                    $sortString .= ',';
                }

                $sortString .= $field . ' ' . $order;
            }
        }

        return $sortString;
    }

    /**
     * Build filter query from search filter parameter.
     *
     * The values of Filter are written into the filter query as they are given (field names are mapped, values
     * are not quoted or checked), so a value may carry any query syntax. They must come from code or settings,
     * never from request input; a value a visitor chooses has to be checked or escaped by the caller first.
     * The request filters of fetch( 'ezfind', 'filterParameters' ) are: ezfModuleFunctionCollection::requestFilterList()
     * lets through only field names, and per value a number or one quoted phrase.
     *
     * @deprecated api is way too limited now
     * @todo for eZ Find 2.0: rework this for recursive boolean combinations and a few more filter types, the possible combinations are almost infinite for pure Solr syntax
     * @param array Parameter list array.
     *              The normal simple use is an array of type: array( '<field name>', <value> ).
     *              The value may also be an array containing values.
     *
     *              Examples :
     * <code>
     *                   $parameters = array( 'article/title:hello' );
     *                   $parameters = array( 'article/title' => 'hello' );
     *                   $parameters = array( 'article/rating' => '[1 TO 10]' );
     *                   $parameters = array( 'article/rating' => '[1 TO 10]',
     *                                        'article/body:hello' );
     *                   $parameters = array( 'or',
     *                                        'article/rating' => '[1 TO 10]',
     *                                        'article/body:hello' );
     *                   $parameters = array( 'or',
     *                                        array( 'or',
     *                                               'article/rating' => '[1 TO 10]',
     *                                               'article/body:hello' ),
     *                                        array( 'and',
     *                                               'article/rating' => '[10 TO 20]',
     *                                               'article/body:goodbye' ) );
     * </code>
     * @return string Filter Query. Null if no filter parameters are in
     * the $parameterList
     */
    protected function getParamFilterQuery( $parameterList )
    {
        if ( empty( $parameterList['Filter'] ) )
        {
            return null;
        }

        $booleanOperator = $this->getBooleanOperatorFromFilter( $parameterList['Filter'] );

        $filterQueryList = array();
        foreach ( $parameterList['Filter'] as $baseName => $value )
        {
            if ( !is_array( $value ) and strpos( $value, ':' ) !== false && is_numeric( $baseName ) )
            {
                // split at the first colon, leave the rest intact
                list( $baseName, $value ) = explode( ':', $value, 2 );
            }

            if ( is_array( $value ) )
            {
                $filterQueryList[] = '( ' . $this->getParamFilterQuery( array( 'Filter' => $value ) ) . ' )';
            }
            else
            {
                if ( $value !== null )
                {
                    // Exception to the generic processing : when a subtree filter is applied, the search plugin needs to be notified
                    // to be able to pick the right URL for objects, the main URL of which is located outside the subtree filter scope.
                    if ( $baseName ==  'path' )
                    {
                        if ( isset( $this->searchPluginInstance->postSearchProcessingData['subtree_array'] ) )
                            $this->searchPluginInstance->postSearchProcessingData['subtree_array'][] = $value;
                        else
                            $this->searchPluginInstance->postSearchProcessingData['subtree_array'] = array( $value );
                    }

                    // Get internal field name. Returns a class ID filter if applicable. Add it as an implicit filter if needed.
                    $baseNameInfo = eZSolr::getFieldName( $baseName, true, 'filter' );
                    if ( is_array( $baseNameInfo ) and isset( $baseNameInfo['contentClassId'] ) )
                    {
                        $filterQueryList[] = '( ' . eZSolr::getMetaFieldName( 'contentclass_id' ) . ':' . $baseNameInfo['contentClassId'] . ' AND ' . $baseNameInfo['fieldName'] . ':' . $value . ' )' ;
                    }
                    else
                    {
                        // Note that $value needs to be escaped if it unintentionally contains Solr reserved characters
                        $filterQueryList[] = $baseNameInfo . ':' . $value;
                    }
                }
            }
        }

        return implode( " $booleanOperator ", $filterQueryList );
    }

    /**
     * Identifies which boolean operator to use when building the filter string ( fq parameter in the final Solr raw request )
     * Removes the operator from the array, if existing.
     *
     * @param array &$filter Filter array processed in self::getParamFilterQuery
     * @returns string The boolean operator to use. Default to 'AND'
     * @see ezfeZPSolrQueryBuilder::getParamFilterQuery
     */
    protected function getBooleanOperatorFromFilter( &$filter )
    {
        if ( isset( $filter[0] ) and is_string( $filter[0] ) and in_array( $filter[0], self::$allowedBooleanOperators ) )
        {
            $retVal = strtoupper( $filter[0] );
            unset( $filter[0] );
            return  $retVal;
        }
        else
            return self::DEFAULT_BOOLEAN_OPERATOR;
    }

    /**
     * Analyze the string, and decide if quotes should be added or not.
     *
     * @param string String
     *
     * @return string String with quotes added if needed.
     * @deprecated
     */
    static function quoteIfNeeded( $value )
    {
        $quote = '';
        if ( strpos( $value, ' ' ) !== false )
        {
            $quote = '"';
            if ( strpos( trim( $value ), '(' ) === 0 )
            {
                $quote = '';
            }
        }
        return $quote . $value . $quote;
    }

    /**
     * Build facet parameter list. This function extracts the facet parameter from
     * the ezfeZPSolrQueryBuilder::search( ...,$params parameter.
     *
     * A facet's 'query' (facet.query), like its field, prefix and the other facet values, is passed to Solr as it
     * is given. It must come from code or settings, never from request input.
     *
     * @todo specify dedicated facet fields (may be mapped to sort fields)
     *
     * @param array Parameter list array
     *
     * @return array List of Facet query parameter. The facet parameter corrosponds to
     * the parameters defined here : http://wiki.apache.org/solr/SimpleFacetParameters
     */
    protected function buildFacetQueryParamList( $parameterList )
    {
        $parameterList = array_change_key_case( $parameterList, CASE_LOWER );
        $queryParamList = array();

        if ( empty( $parameterList['facet'] ) )
        {
            return $queryParamList;
        }

        // Loop through facet definitions, and build facet query.
        foreach ( $parameterList['facet'] as $facetDefinition )
        {
            if ( empty( $facetDefinition['field'] ) and
                 empty( $facetDefinition['query'] ) and
                 empty( $facetDefinition['date'] ) and
                 empty( $facetDefinition['range'] ) and
                 empty( $facetDefinition['prefix'] ) )
            {
                eZDebug::writeDebug( 'No facet field or query provided.', __METHOD__ );
                continue;
            }

            $queryPart = array();
            if ( !empty( $facetDefinition['field'] ) )
            {
                switch( $facetDefinition['field'] )
                {
                    case 'author':
                    {
                        $queryPart['field'] = eZSolr::getMetaFieldName( 'owner_id', 'facet' );
                    } break;

                    case 'class':
                    {
                        $queryPart['field'] = eZSolr::getMetaFieldName( 'contentclass_id', 'facet' );
                    } break;

                    case 'installation':
                    {
                        $queryPart['field'] = eZSolr::getMetaFieldName( 'installation_id', 'facet' );
                    } break;

                    case 'translation':
                    {
                        $queryPart['field'] = eZSolr::getMetaFieldName( 'language_code', 'facet' );
                    } break;

                    default:
                    {
                        $fieldName = eZSolr::getFieldName( $facetDefinition['field'], false, 'facet' );
                        if ( !$fieldName and empty( $facetDefinition['date'] ) )
                        {
                            eZDebug::writeNotice( 'Facet field does not exist in local installation, but may still be valid: ' .
                                                  $facetDefinition['field'],
                                                  __METHOD__ );
                            continue 2;
                        }
                        $queryPart['field'] = $fieldName;
                    } break;
                }
            }

            // Get query part.
            if ( !empty( $facetDefinition['query'] ) )
            {
                list( $field, $query ) = explode( ':', $facetDefinition['query'], 2 );

                $field = eZSolr::getFieldName( $field, false, 'facet' );
                if ( !$field )
                {
                    eZDebug::writeNotice( 'Invalid query field provided: ' . $facetDefinition['query'],
                                          __METHOD__ );
                    continue;
                }

                $queryPart['query'] = $field . ':' . $query;
            }

            // Get prefix.
            // TODO: make this per mandatory per field in order to construct f.<fieldname>.facet.prefix queries
            if ( !empty( $facetDefinition['prefix'] ) )
            {
                $queryPart['prefix'] = $facetDefinition['prefix'];
            }

            // range facets: fill the $queryParamList array directly
            if ( !empty( $facetDefinition['range'])
                    && !empty( $facetDefinition['range']['field'] )
                    && !empty( $facetDefinition['range']['start'] )
                    && !empty( $facetDefinition['range']['end'])
                    && !empty( $facetDefinition['range']['gap']))
            {
                $fieldName = '';


                switch( $facetDefinition['range']['field'] )
                {
                    case 'published':
                    {
                        $fieldName = eZSolr::getMetaFieldName( 'published', 'facet' );
                    } break;

                    case 'modified':
                    {
                        $fieldName = eZSolr::getMetaFieldName( 'modified', 'facet' );
                    } break;

                    default:
                    {
                        $fieldName = eZSolr::getFieldName( $facetDefinition['field'], false, 'facet' );
                    }
                }

                $perFieldRangePrefix = 'f.' . $fieldName . '.facet.range';

                $queryParamList['facet.range'] = $fieldName;

                $queryParamList[$perFieldRangePrefix . '.start'] = $facetDefinition['range']['start'];
                $queryParamList[$perFieldRangePrefix . '.end']   = $facetDefinition['range']['end'];
                $queryParamList[$perFieldRangePrefix . '.gap']   = $facetDefinition['range']['gap'];

                if( !empty( $facetDefinition['range']['hardend']))
                {
                    $queryParamList[$perFieldRangePrefix . '.hardend'] = $facetDefinition['range']['hardend'];
                }

                if( !empty( $facetDefinition['range']['include']))
                {
                    $queryParamList[$perFieldRangePrefix . '.include'] = $facetDefinition['range']['include'];
                }

                if( !empty( $facetDefinition['range']['other']))
                {
                    $queryParamList[$perFieldRangePrefix . '.other']   = $facetDefinition['range']['other'];
                }
            }

            // Get sort option.
            if ( !empty( $facetDefinition['sort'] ) )
            {
                switch( strtolower( $facetDefinition['sort'] ) )
                {
                    case 'count':
                    {
                        $queryPart['sort'] = 'true';
                    } break;

                    case 'alpha':
                    {
                        $queryPart['sort'] = 'false';
                    } break;

                    default:
                    {
                        eZDebug::writeWarning( 'Invalid sort option provided: ' . $facetDefinition['sort'],
                                               __METHOD__ );
                    } break;
                }
            }

            // Get limit option
            if ( !empty( $facetDefinition['limit'] ) )
            {
                $queryPart['limit'] = $facetDefinition['limit'];
            }
            else
            {
                $queryPart['limit'] = ezfeZPSolrQueryBuilder::FACET_LIMIT;
            }

            // Get offset
            if ( !empty( $facetDefinition['offset'] ) )
            {
                $queryPart['offset'] = $facetDefinition['offset'];
            }
            else
            {
                $queryPart['offset'] = ezfeZPSolrQueryBuilder::FACET_OFFSET;
            }

            // Get mincount
            if ( !empty( $facetDefinition['mincount'] ) )
            {
                $queryPart['mincount'] = $facetDefinition['mincount'];
            }
            else
            {
                $queryPart['mincount'] = ezfeZPSolrQueryBuilder::FACET_MINCOUNT;
            }

            // Get missing option.
            if ( !empty( $facetDefinition['missing'] ) )
            {
                $queryPart['missing'] = 'true';
            }

            // Get date start option - may add validation later.
            if ( !empty( $facetDefinition['date'] ) )
            {
                $fieldName = eZSolr::getFieldName( $facetDefinition['date'], false, 'facet' );
                if ( !$fieldName )
                {
                    eZDebug::writeNotice( 'Facet field does not exist in local installation, but may still be valid: ' .
                                          $facetDefinition['date'],
                                          __METHOD__ );
                    continue;
                }
                else
                {
                    $queryPart['date'] = $fieldName;
                }
            }


            // Get date start option - may add validation later.
            if ( !empty( $facetDefinition['date.start'] ) )
            {
                $queryPart['date.start'] = $facetDefinition['date.start'];
            }

            // Get date end option - may add validation later.
            if ( !empty( $facetDefinition['date.end'] ) )
            {
                $queryPart['date.end'] = $facetDefinition['date.end'];
            }

            // Get date gap option - may add validation later.
            if ( !empty( $facetDefinition['date.gap'] ) )
            {
                $queryPart['date.gap'] = $facetDefinition['date.gap'];
            }

            // Get date hardend option - may add validation later.
            if ( !empty( $facetDefinition['date.hardend'] ) )
            {
                $queryPart['date.hardend'] = $facetDefinition['date.hardend'];
            }

            // Get date hardend option - may add validation later.
            if ( !empty( $facetDefinition['date.other'] ) )
            {
                switch( strtolower( $facetDefinition['date.other'] ) )
                {
                    case 'before':
                    case 'after':
                    case 'between':
                    case 'none':
                    case 'all':
                        $queryPart['date.other'] = strtolower( $facetDefinition['date.other'] );
                        break;
                    default:
                        eZDebug::writeWarning( 'Invalid option gived for date.other: ' . $facetDefinition['date.other'],
                                               __METHOD__ );
                }
            }

            if ( !empty( $queryPart ) )
            {
                foreach ( $queryPart as $key => $value )
                {
                    // check for fully prepared parameter names, like the per field options
                    if ( strpos( $key, 'f.' ) === 0 )
                    {
                        $queryParamList[$key] = $value;
                    }
                    elseif (
                        $key !== 'field'
                        && !empty( $queryParamList['facet.' . $key] )
                        && isset( $queryPart['field'] )
                       )
                    {
                        // local override for one given facet
                        $queryParamList['f.' . $queryPart['field'] . '.facet.' . $key][] = $value;
                    }
                    else
                    {
                        // global value
                        $queryParamList['facet.' . $key][] = $value;
                    }
                }
            }
        }

        if ( !empty( $queryParamList ) )
        {
            $queryParamList['facet'] = 'true';
        }
        return $queryParamList;
    }

    /**
     * Check if search string requires certain field types to be excluded from the search
     *
     * @param string Search string
     *        If null, exclude all non text fields
     * @todo make sure this function is in sync with schema.xml
     * @todo decide wether or not to drop this, pure numeric and date values are
     *       most likely to go into filters, not the main query
     *
     * @return array List of field types to exclude from the search
     */
    protected function fieldTypeExludeList( $searchText )
    {
        if ( is_null( $searchText ) )
        {
            return array( 'date', 'boolean', 'int', 'long', 'float', 'double', 'sint', 'slong', 'sfloat', 'sdouble' );
        }

        $excludeFieldList = array();
        // Check if search text is a date.
        if ( strtotime( $searchText ) === false )
        {
            $excludeFieldList[] = 'date';
        }
        if  ( strtolower( $searchText ) !== 'true' &&
              strtolower( $searchText ) !== 'false' )
        {
            $excludeFieldList[] = 'boolean';
        }
        if ( !is_numeric( $searchText ) )
        {
            $excludeFieldList[] = 'int';
            $excludeFieldList[] = 'long';
            $excludeFieldList[] = 'float';
            $excludeFieldList[] = 'double';
            $excludeFieldList[] = 'sint';
            $excludeFieldList[] = 'slong';
            $excludeFieldList[] = 'sfloat';
            $excludeFieldList[] = 'sdouble';
        }

        return $excludeFieldList;
    }

    /**
     * Generate boost query on search. This boost is configured boost the following criterias:
     * - local installation
     * - Language priority
     *
     * @return boostQuery
     */
    protected function boostQuery()
    {
        // Local installation boost
        $boostQuery = eZSolr::getMetaFieldName( 'installation_id' ) . ':' . eZSolr::installationID() . '^1.5';
        $ini = eZINI::instance();

        // Language boost. Only boost 3 first languages.
        $languageBoostList = array( '1.2', '1.0', '0.8' );
        foreach ( $ini->variable( 'RegionalSettings', 'SiteLanguageList' ) as $idx => $languageCode )
        {
            if ( empty( $languageBoostList[$idx] ) )
            {
                break;
            }
            $boostQuery .= ' ' . eZSolr::getMetaFieldName( 'language_code' ) . ':' . $languageCode . '^' . $languageBoostList[$idx];
        }

        // @TODO : User defined boosts through ini settings
        return $boostQuery;
    }

    /**
     * Generate class query filter.
     *
     * @param mixed $contentClassIdent eZContentClass id, identifier or list of ids.
     *
     * @return string Content class query filter. Returns null if invalid
     *         $contentClassIdent is provided.
     */
    protected function getContentClassFilterQuery( $contentClassIdent )
    {
        if ( empty( $contentClassIdent ) )
        {
            return null;
        }

        // Ids are written into the filter query: a numeric class id counts only as a positive integer of digits
        // (not "1e0", "+1" or a list inside the list), anything else is looked up as a class identifier
        $classQueryParts = array();
        foreach ( is_array( $contentClassIdent ) ? $contentClassIdent : array( $contentClassIdent ) as $classIdent )
        {
            $classID = $this->classIdentToID( $classIdent );
            if ( $classID === null )
            {
                continue;
            }
            if ( $classID === false )
            {
                eZDebug::writeError( 'Unknown class_id filtering parameter: ' . ( is_scalar( $classIdent ) ? $classIdent : gettype( $classIdent ) ), __METHOD__ );
                continue;
            }
            $classQueryParts[] = eZSolr::getMetaFieldName( 'contentclass_id' ) . ':' . $classID;
        }

        if ( $classQueryParts )
        {
            return implode( ' OR ', $classQueryParts );
        }

        eZDebug::writeError( 'No valid content class', __METHOD__ );

        return null;
    }

    /**
     * The filters of a query that does not go through buildSearch(), such as the autocomplete of
     * eZFindServerCallFunctions: the class filter of $contentClassIdent by the rule of getContentClassFilterQuery()
     * (a list of ids and identifiers; when it is given but names no class, a filter that matches nothing), and the
     * policy limitation of the current user.
     *
     * @param mixed $contentClassIdent null, '' or array() for no class filter
     * @return string[] filter queries, each to be ANDed
     */
    public function classAndPolicyFilterQueries( $contentClassIdent )
    {
        $filterQueries = array();
        if ( $contentClassIdent !== null && $contentClassIdent !== '' && $contentClassIdent !== array() )
        {
            $classFilter = $this->getContentClassFilterQuery( $contentClassIdent );
            $filterQueries[] = $classFilter !== null ? $classFilter : eZSolr::getMetaFieldName( 'contentclass_id' ) . ':0';
        }
        $policyFilter = $this->policyLimitationFilterQuery();
        if ( $policyFilter !== false && $policyFilter !== null && $policyFilter !== '' )
        {
            $filterQueries[] = $policyFilter;
        }
        return $filterQueries;
    }

    /**
     * The id of one entry of a class filter: a positive integer (given as an integer or as digits), or the id of
     * the class a text names as its identifier.
     *
     * @param mixed $classIdent
     * @return int|false|null the class id; false when it names no class; null for an empty entry
     */
    protected function classIdentToID( $classIdent )
    {
        if ( is_int( $classIdent ) )
        {
            return $classIdent > 0 ? $classIdent : false;
        }
        if ( !is_string( $classIdent ) )
        {
            return false;
        }
        $classIdent = trim( $classIdent );
        if ( $classIdent === '' )
        {
            return null;
        }
        if ( ctype_digit( $classIdent ) )
        {
            return (int)$classIdent > 0 ? (int)$classIdent : false;
        }
        $classID = (int)$this->fetchClassIDByIdentifier( $classIdent );
        return $classID > 0 ? $classID : false;
    }

    /**
     * @param string $identifier
     * @return int|false
     */
    protected function fetchClassIDByIdentifier( $identifier )
    {
        $class = eZContentClass::fetchByIdentifier( $identifier );
        return $class ? (int)$class->attribute( 'id' ) : false;
    }

    /**
     * The node or object id of a More Like This search by id ('nid', 'oid'): an integer above 0, or a string of
     * digits (spaces around it allowed) above 0; anything else is 0, which no node or object has, so the search
     * finds nothing.
     *
     * @param mixed $query
     * @return int
     */
    public static function moreLikeThisID( $query )
    {
        if ( is_string( $query ) )
        {
            $query = trim( $query );
            $query = ctype_digit( $query ) ? (int)$query : 0;
        }
        return is_int( $query ) && $query > 0 ? $query : 0;
    }

    /**
     * The ids of a search parameter as positive integers: one id or a list of them, as a search parameter or a
     * request gives it. Every value is cast with (int) and kept when it is above 0; values that are no number
     * (an array inside the list, an object, null, true or false) are left out, and so are duplicates. The ids are
     * written into Solr filter queries, so nothing but an integer may reach them.
     *
     * @param mixed $value
     * @return int[] in the order given
     */
    public static function positiveIntegerIDList( $value )
    {
        if ( !is_array( $value ) )
        {
            $value = array( $value );
        }
        $idList = array();
        foreach ( $value as $item )
        {
            if ( !is_int( $item ) && !is_string( $item ) && !is_float( $item ) )
            {
                continue;
            }
            $id = (int)$item;
            if ( $id > 0 )
            {
                $idList[$id] = $id;
            }
        }
        return array_values( $idList );
    }

    /**
     * The offset of a search: SearchOffset as an integer, 0 when it is missing or below 0 (Solr refuses a
     * negative start).
     *
     * @param array $params
     * @return int
     */
    public static function searchOffset( $params )
    {
        $offset = isset( $params['SearchOffset'] ) && is_scalar( $params['SearchOffset'] ) ? (int)$params['SearchOffset'] : 0;
        return $offset > 0 ? $offset : 0;
    }

    /**
     * The number of results of a search: SearchLimit as an integer, 10 when it is missing or not above 0.
     *
     * @param array $params
     * @return int
     */
    public static function searchLimit( $params )
    {
        $limit = isset( $params['SearchLimit'] ) && is_scalar( $params['SearchLimit'] ) ? (int)$params['SearchLimit'] : 0;
        return $limit > 0 ? $limit : 10;
    }

    /**
     * The class attributes a search may be limited to: those of the given ids that exist, and, when classes are
     * given, belong to one of them. An id of an attribute that does not exist or belongs to another class is left
     * out (with a debug warning); before, the first stopped the search with a TypeError and the second gave no
     * result at all.
     *
     * @param mixed $classAttributeIdent a class attribute id or a list of them
     * @param mixed $contentClassIdent the class filter of the search: an id, an identifier, a list of them, or false
     * @return int[]
     */
    protected function validClassAttributeIDList( $classAttributeIdent, $contentClassIdent )
    {
        $classIDList = $this->classIDList( $contentClassIdent );
        $validIDList = array();
        foreach ( self::positiveIntegerIDList( $classAttributeIdent ) as $classAttributeID )
        {
            $classAttribute = $this->fetchClassAttribute( $classAttributeID );
            if ( !$classAttribute instanceof eZContentClassAttribute )
            {
                eZDebug::writeWarning( "Class attribute $classAttributeID does not exist and is left out of the search", __METHOD__ );
                continue;
            }
            if ( $classIDList && !in_array( (int)$classAttribute->attribute( 'contentclass_id' ), $classIDList, true ) )
            {
                eZDebug::writeWarning( "Class attribute $classAttributeID is not one of the classes searched and is left out", __METHOD__ );
                continue;
            }
            $validIDList[] = $classAttributeID;
        }
        return $validIDList;
    }

    /**
     * The class ids of a class filter: ids as positive integers, identifiers as the id of their class.
     *
     * @param mixed $contentClassIdent an id, an identifier, a list of them, or false
     * @return int[]
     */
    protected function classIDList( $contentClassIdent )
    {
        if ( $contentClassIdent === false || $contentClassIdent === null || $contentClassIdent === '' )
        {
            return array();
        }
        $classIDList = array();
        foreach ( is_array( $contentClassIdent ) ? $contentClassIdent : array( $contentClassIdent ) as $classIdent )
        {
            $classID = $this->classIdentToID( $classIdent );
            if ( is_int( $classID ) )
            {
                $classIDList[] = $classID;
            }
        }
        return $classIDList;
    }

    /**
     * @param int $classAttributeID
     * @return eZContentClassAttribute|null
     */
    protected function fetchClassAttribute( $classAttributeID )
    {
        return eZContentClassAttribute::fetch( $classAttributeID );
    }

    /**
     * Generate section query filter.
     *
     * @param mixed $sectionIdent eZSection id or list of ids.
     *
     * @return string Section query filter. Returns null if invalid
     *         $sectionIdent is provided.
     */
    protected function getSectionFilterQuery( $sectionIdent )
    {
        if ( empty( $sectionIdent ) )
        {
            return null;
        }

        if ( is_array( $sectionIdent ) )
        {
            $sectionQueryParts = array();
            foreach ( $sectionIdent as $sectionID )
            {
                $sectionID = trim( (string)$sectionID );
                if ( ctype_digit( $sectionID ) && (int)$sectionID > 0 )
                {
                    $sectionQueryParts[] = eZSolr::getMetaFieldName( 'section_id' ) . ':' . $sectionID;
                }
                else
                {
                    eZDebug::writeError( "Unknown section_id filtering parameter: $sectionID", __METHOD__ );
                }
            }

            return $sectionQueryParts ? implode( ' OR ', $sectionQueryParts ) : null;
        }

        $sectionIdent = trim( (string)$sectionIdent );
        if ( ctype_digit( $sectionIdent ) && (int)$sectionIdent > 0 )
        {
            return eZSolr::getMetaFieldName( 'section_id' ) . ':' . $sectionIdent;
        }

        eZDebug::writeError( 'No valid section id', __METHOD__ );

        return null;
    }

    /**
     * Create policy limitation query.
     *
     * @param array $limitation Override the limitation of the user. Same format as the return of eZUser::hasAccessTo()
     * @param boolean $ignoreVisibility Set to true for the visibility to be ignored
     * @return string Lucene/Solr query string which can be used as filter query for Solr
     */
    protected function policyLimitationFilterQuery( $limitation = null, $ignoreVisibility = null )
    {
        $eZFindIni = eZINI::instance( 'ezfind.ini' );

        $filterQuery = false;
        $policies = array();

        $pathFieldName = $ignoreVisibility ? eZSolr::getMetaFieldName( 'path' ) : eZSolr::getMetaFieldName( 'visible_path' );

        if ( is_array( $limitation ) )
        {
            if ( empty( $limitation ) )
            {
                $limitation['accessWord'] = 'yes';
            }
        }
        else
        {
            $limitation = eZUser::currentUser()->hasAccessTo( 'content', 'read' );
        }

        if ( isset( $limitation['accessWord'] ) )
        {
            switch ( $limitation['accessWord'] )
            {
                case 'limited':
                    if ( isset( $limitation['policies'] ) )
                    {
                        $policies = $limitation['policies'];
                        break;
                    }
                    // break omitted, "limited" without policies == "no"
                case 'no':
                    return ' NOT *:* ';
                case 'yes':
                    break;
                default:
                    return false;
            }
        }


        // Add limitations for filter query based on local permissions.


        $limitationHash = array(
            'Class'        => eZSolr::getMetaFieldName( 'contentclass_id' ),
            'Section'      => eZSolr::getMetaFieldName( 'section_id' ),
            'User_Section' => eZSolr::getMetaFieldName( 'section_id' ),
            'Subtree'      => eZSolr::getMetaFieldName( 'path_string' ),
            'User_Subtree' => eZSolr::getMetaFieldName( 'path_string' ),
            'Node'         => eZSolr::getMetaFieldName( 'main_node_id' ),
            'Owner'        => eZSolr::getMetaFieldName( 'owner_id' ),
            'Group'        => eZSolr::getMetaFieldName( 'owner_group_id' ),
            'ObjectStates' => eZSolr::getMetaFieldName( 'object_states' ) );

        $filterQueryPolicies = array();

        // policies are concatenated with OR
        foreach ( $policies as $limitationList )
        {
            // policy limitations are concatenated with AND
            // except for locations policity limitations, concatenated with OR
            $filterQueryPolicyLimitations = array();
            $policyLimitationsOnLocations = array();

            foreach ( $limitationList as $limitationType => $limitationValues )
            {
                // limitation values of one type in a policy are concatenated with OR
                $filterQueryPolicyLimitationParts = array();

                switch ( $limitationType )
                {
                    case 'User_Subtree':
                    case 'Subtree':
                    {
                        foreach ( $limitationValues as $limitationValue )
                        {
                            $pathString = trim( $limitationValue, '/' );
                            $pathArray = explode( '/', $pathString );
                            // we only take the last node ID in the path identification string
                            $subtreeNodeID = array_pop( $pathArray );
                            $policyLimitationsOnLocations[] = $pathFieldName . ':' . $subtreeNodeID;
                            if ( isset( $this->searchPluginInstance->postSearchProcessingData['subtree_limitations'] ) )
                                $this->searchPluginInstance->postSearchProcessingData['subtree_limitations'][] = $subtreeNodeID;
                            else
                                $this->searchPluginInstance->postSearchProcessingData['subtree_limitations'] = array( $subtreeNodeID );
                        }
                    } break;

                    case 'Node':
                    {
                        foreach ( $limitationValues as $limitationValue )
                        {
                            $pathString = trim( $limitationValue, '/' );
                            $pathArray = explode( '/', $pathString );
                            // we only take the last node ID in the path identification string
                            $nodeID = array_pop( $pathArray );
                            $policyLimitationsOnLocations[] = $limitationHash[$limitationType] . ':' . $nodeID;
                            if ( isset( $this->searchPluginInstance->postSearchProcessingData['subtree_limitations'] ) )
                                $this->searchPluginInstance->postSearchProcessingData['subtree_limitations'][] = $nodeID;
                            else
                                $this->searchPluginInstance->postSearchProcessingData['subtree_limitations'] = array( $nodeID );
                        }
                    } break;

                    case 'Group':
                    {
                        foreach ( eZUser::currentUser()->attribute( 'contentobject' )->attribute( 'parent_nodes' ) as $groupID )
                        {
                            $filterQueryPolicyLimitationParts[] = $limitationHash[$limitationType] . ':' . $groupID;
                        }
                    } break;

                    case 'Owner':
                    {
                        $filterQueryPolicyLimitationParts[] = $limitationHash[$limitationType] . ':' . eZUser::currentUser()->attribute ( 'contentobject_id' );
                    } break;

                    case 'Class':
                    case 'Section':
                    case 'User_Section':
                    {
                        foreach ( $limitationValues as $limitationValue )
                        {
                            $filterQueryPolicyLimitationParts[] = $limitationHash[$limitationType] . ':' . $limitationValue;
                        }
                    } break;

                    default :
                    {
                        //hacky, object state limitations reference the state group name in their
                        //limitation
                        //hence the following match on substring

                        if ( strpos( $limitationType, 'StateGroup' ) !== false )
                        {
                            foreach ( $limitationValues as $limitationValue )
                            {
                                $filterQueryPolicyLimitationParts[] = $limitationHash['ObjectStates'] . ':' . $limitationValue;
                            }
                        }
                        else
                        {
                            $extensionFilter = $this->extensionLimitationFilterQuery( $limitationType, $limitationValues );
                            if ( $extensionFilter === null )
                            {
                                eZDebug::writeDebug( $limitationType, __METHOD__ . ' unknown limitation type: ' . $limitationType );
                                continue 2;
                            }
                            // ANDed with the other limitations of the policy, as a part of its own
                            $filterQueryPolicyLimitations[] = $extensionFilter;
                            continue 2;
                        }
                    }
                }

                if ( !empty( $filterQueryPolicyLimitationParts ) )
                    $filterQueryPolicyLimitations[] = '( ' . implode( ' OR ', $filterQueryPolicyLimitationParts ) . ' )';
            }

            // Policy limitations on locations (node and/or subtree) need to be concatenated with OR
            // unlike the other types of limitation
            if ( !empty( $policyLimitationsOnLocations ) )
            {
                $filterQueryPolicyLimitations[] = '( ' . implode( ' OR ', $policyLimitationsOnLocations ) . ')';
            }

            if ( !empty( $filterQueryPolicyLimitations ) )
            {
                $filterQueryPolicies[] = '( ' . implode( ' AND ', $filterQueryPolicyLimitations ) . ')';
            }
        }

        if ( !empty( $filterQueryPolicies ) )
        {
            $filterQuery = implode( ' OR ', $filterQueryPolicies );
        }


        // Add limitations for allowing search of other installations.
        $anonymousPart = '';
        if ( $eZFindIni->variable( 'SiteSettings', 'SearchOtherInstallations' ) == 'enabled' )
        {
            $anonymousPart = ' OR ' . eZSolr::getMetaFieldName( 'anon_access' ) . ':true ';
        }

        if ( !empty( $filterQuery ) )
        {
            $filterQuery = '((' . eZSolr::getMetaFieldName( 'installation_id' ) . ':' . eZSolr::installationID() . ' AND (' . $filterQuery . ')) ' . $anonymousPart . ' )';
        }
        else
        {
            $filterQuery = '(' . eZSolr::getMetaFieldName( 'installation_id' ) . ':' . eZSolr::installationID() . $anonymousPart . ')';
        }

        // Add ignore visibility condition, either explicitely set to boolean false or not specified
        if ( $ignoreVisibility === false || $ignoreVisibility === null )
        {
            $filterQuery .= ' AND ' . eZSolr::getMetaFieldName( 'is_invisible' ) . ':false';
        }

        eZDebugSetting::writeDebug( 'extension-ezfind-query', $filterQuery, __METHOD__ );

        return $filterQuery;
    }

    /**
     * Returns the filter of a policy limitation that an extension defines (a limitation handler registered in
     * site.ini [RoleSettings] LimitationHandlers[]), asked of the kernel's ezpContentLimitation::solrFilter().
     *
     * The filter is ANDed with the other limitations of the policy. It never widens a search: when the kernel gives
     * no filter, or something that is not one, the filter matches no document (self::DENY_FILTER), so the policy
     * gives no access in searches.
     *
     * Null when the kernel has no solrFilter() (an Exponential kernel before 6.0.15, or another kernel) and for a
     * limitation the kernel evaluates itself that eZ Find does not translate: the limitation is then left out of
     * the policy, as before.
     *
     * @param string $limitationType
     * @param array $limitationValues
     * @return string|null
     */
    protected function extensionLimitationFilterQuery( $limitationType, $limitationValues )
    {
        $class = $this->contentLimitationClass;
        if ( !is_string( $class ) || !class_exists( $class ) || !method_exists( $class, 'solrFilter' ) )
        {
            return null;
        }
        if ( method_exists( $class, 'isKernelLimitation' ) && call_user_func( array( $class, 'isKernelLimitation' ), $limitationType ) )
        {
            return null;
        }

        $filter = call_user_func( array( $class, 'solrFilter' ), $limitationType, is_array( $limitationValues ) ? $limitationValues : array( $limitationValues ) );
        if ( !is_string( $filter ) || !preg_match( '/^\(.*\)$/s', trim( $filter ) ) )
        {
            eZDebug::writeWarning( "The kernel gave no search filter for the limitation $limitationType; the policy gives no access in searches", __METHOD__ );
            return self::DENY_FILTER;
        }
        return trim( $filter );
    }

    /**
     * Get an array of class attribute identifiers based on either a class attribute
     * list, or a content classes list
     *
     * @param array $classIDArray
     *        Classes to search in. Either an array of class ID, class identifiers,
     *        a class ID or a class identifier.
     *        Using numerical attribute/class identifiers for $classIDArray is more efficient.
     * @param array $classAttributeID
     *        Class attributes to search in. Either an array of class attribute id,
     *        or a single class attribute. Literal identifiers are not allowed.
     * @param array $fieldTypeExcludeList
     *        filter list. List of field types to exclude. ( set to empty array by default ).
     *
     * @return array List of solr field names.
     */
    protected function getClassAttributes( $classIDArray = false,
        $classAttributeIDArray = false,
        $fieldTypeExcludeList = null )
    {
        eZDebug::createAccumulator( 'Class attribute list', 'eZ Find' );
        eZDebug::accumulatorStart( 'Class attribute list' );
        $fieldArray = array();

        $classAttributeArray = array();

        // classAttributeIDArray = simple integer (content class attribute ID)
        // or array of integers (content class attribute IDs); ids that name no attribute are left out
        if ( ( is_numeric( $classAttributeIDArray ) and $classAttributeIDArray > 0 ) or is_array( $classAttributeIDArray ) )
        {
            foreach ( self::positiveIntegerIDList( $classAttributeIDArray ) as $classAttributeID )
            {
                $classAttribute = $this->fetchClassAttribute( $classAttributeID );
                if ( $classAttribute instanceof eZContentClassAttribute )
                {
                    $classAttributeArray[] = $classAttribute;
                }
            }
        }

        // no class attribute list given, we need a class list
        // this block will create the class attribute array based on $classIDArray
        if ( empty( $classAttributeArray ) )
        {
            // Fetch class list.
            $condArray = array( "is_searchable" => 1,
                                "version" => eZContentClass::VERSION_STATUS_DEFINED );
            if ( !$classIDArray )
            {
                $classIDArray = array();
            }
            else if ( !is_array( $classIDArray ) )
            {
                $classIDArray = array( $classIDArray );
            }
            // literal class identifiers are converted to numerical ones
            $tmpClassIDArray = $classIDArray;
            $classIDArray = array();
            foreach ( $tmpClassIDArray as $key => $classIdentifier )
            {
                if ( !is_numeric( $classIdentifier ) )
                {
                    if ( !$contentClass = eZContentClass::fetchByIdentifier( $classIdentifier, false ) )
                    {
                        eZDebug::writeWarning( "Unknown content class identifier '$classIdentifier'", __METHOD__ );
                    }
                    else
                    {
                        $classIDArray[] = $contentClass['id'];
                    }
                }
                else
                {
                    $classIDArray[] = $classIdentifier;
                }
            }

            if ( !empty( $classIDArray ) )
            {
                $condArray['contentclass_id'] = array( $classIDArray );
            }

            $classAttributeArray = eZContentClassAttribute::fetchFilteredList( $condArray );
        }

        // $classAttributeArray now contains a list of eZContentClassAttribute
        // we can use to construct the list of fields solr should search in
        // @TODO : retrieve sub attributes here. Mind the types !
        foreach ( $classAttributeArray as $classAttribute )
        {
            $fieldArray = array_merge( ezfSolrDocumentFieldBase::getFieldNameList( $classAttribute, $fieldTypeExcludeList ), $fieldArray );
        }

        // the array is unified + sorted in order to make it consistent
        $fieldArray = array_unique( $fieldArray );
        sort( $fieldArray );

        eZDebug::accumulatorStop( 'Class attribute list' );
        return $fieldArray;
    }

    private function buildSearchResultClusterQuery( $parameterList = array() )
    {
        $result = array( 'clustering' => 'false');
        if ( !empty( $parameterList ) && $parameterList['clustering'] === true )
        {
            $result['clustering'] = 'true';

            unset( $parameterList['clustering'] );

            $allowedParameters = array( 'carrot.algorithm',
                                        'carrot.title',
                                        'carrot.snippet',
                                        'carrot.produceSummary',
                                        'carrot.fragSize',
                                        'carrot.numDescriptions' );

            foreach ($allowedParameters as $parameter)
            {
                if (isset( $parameterList[$parameter] ) )
                {
                    $result[$parameter] = $parameterList[$parameter];
                }
            }
        }
        return $result;
    }

    /**
     * Array containing the allowed boolean operators for the 'fq' parameter
     * Initialized by the end of this file.
     *
     * @var array
     * @see ezfeZPSolrQueryBuilder::getBooleanOperatorFromFilter
     *
     * @todo need to refactor this: its only valid for the standard Solr request syntax, not for dismax based variants
     * @todo negations should be added as well
     */
    public static $allowedBooleanOperators = array( 'AND', 'and', 'OR', 'or' );

    /**
     * @since eZ Find 2.1
     *
     * Stores the search engine instance which called the query builder.
     * Used to pass back some data to the search engine.
     *
     * @var Object
     * @see ezfeZPSolrQueryBuilder::ezfeZPSolrQueryBuilder
     */
    protected $searchPluginInstance;

    /**
     * Whether the last buildSearch() cut the search text to the words the query fields allow
     * (see eZFindQueryText::limitToClauseBudget()). eZSolr::search() reports it in SearchExtras.
     *
     * @var bool
     */
    public $SearchTextShortened = false;

    /**
     * The kernel class asked for the search filters of the policy limitations extensions define
     *
     * @var string
     * @see ezfeZPSolrQueryBuilder::extensionLimitationFilterQuery()
     */
    protected $contentLimitationClass = 'ezpContentLimitation';

    /**
     * A filter query that matches no document: the filter of a policy limitation that gives no access in searches
     * (the same as the kernel's ezpContentLimitation::DENY_SOLR)
     */
    const DENY_FILTER = '( *:* -*:* )';

    /**
     * Storing the default boolean operator used in building the 'fq' parameter
     *
     * @see ezfeZPSolrQueryBuilder::getBooleanOperatorFromFilter
     */
    const DEFAULT_BOOLEAN_OPERATOR = 'AND';
    const FACET_LIMIT = 20;
    const FACET_OFFSET = 0;
    const FACET_MINCOUNT = 1;
}

?>
