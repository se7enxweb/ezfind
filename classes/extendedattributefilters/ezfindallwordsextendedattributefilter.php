<?php
/**
 * eZFindAllWordsExtendedAttributeFilter
 *
 * Makes every word of the search text required: Solr's minimum match (mm) of
 * the query becomes 100%. A quoted phrase counts as one word.
 *
 * Why: the advanced search of the administration labels its field "Search
 * for all of the following words", and the built-in search engine does
 * exactly that. With eZ Find the edismax default mm of solrconfig.xml
 * (1<1 2<-1 5<-2 7<60%) let one of two words suffice: two words together gave
 * more hits than either word alone. With this filter the field means what it
 * says.
 *
 * In a template:
 *   'extended_attribute_filter', array( hash( 'id', 'ezfindallwords', 'params', hash() ) )
 *
 * Only for the edismax handlers (ezpublish, the default): the standard
 * handlers ignore mm.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package ezfind
 */
class eZFindAllWordsExtendedAttributeFilter implements eZFindExtendedAttributeFilterInterface
{
    /**
     * @param array $queryParams the Solr parameters of the query builder
     * @param array $filterParams none
     * @return array
     */
    public function filterQueryParams( array $queryParams, array $filterParams )
    {
        $queryParams['mm'] = '100%';
        return $queryParams;
    }
}
