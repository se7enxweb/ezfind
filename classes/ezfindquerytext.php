<?php
/**
 * eZFindQueryText
 *
 * The search text as ezfeZPSolrQueryBuilder::buildSearch() hands it to Solr.
 * Pure functions, no kernel, no INI: the callers pass the settings in.
 *
 * Why the words are counted: the ezpublish handler is edismax over a qf of
 * every searchable class attribute, and every word becomes one clause per
 * query field. Solr refuses a query above maxClauseCount (solr.xml
 * maxBooleanClauses, 1024 by default). Measured on an installation with 79
 * query fields: 13 words ran, 15 and 16 failed with "Query contains too many
 * nested clauses; maxClauseCount is set to 1024", although site.ini
 * [SearchSettings] MaximumSearchTextWords=16 allowed them. That setting
 * counts words without the fields, so it cannot prevent this.
 * limitToClauseBudget() keeps as many words as the fields allow, a quoted
 * phrase counting as one.
 *
 * Why * and ? are escaped: edismax reads them as wildcards whatever site.ini
 * [SearchSettings] EnableWildcard says, so the setting had no effect with
 * eZ Find, and a question typed into the search ("what?") ended in a
 * single-character wildcard and found nothing. escapeSyntax() honours
 * EnableWildcard and always escapes a ? that ends a word.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package ezfind
 */
class eZFindQueryText
{
    /** Default of solr.xml maxBooleanClauses. */
    const DEFAULT_MAX_CLAUSES = 1024;

    /**
     * The words of a search text: a quoted phrase is one unit, every other
     * run of non-blank characters one. An unmatched quote is part of the
     * last unit.
     *
     * @param string $text
     * @return string[]
     */
    public static function units( $text )
    {
        preg_match_all( '/[+-]?"[^"]*"\S*|\S+/u', (string) $text, $matches );
        return $matches[0];
    }

    /**
     * How many words a query may have with $fieldCount query fields: every
     * word is one clause per field, and one field's worth is kept free for the
     * phrase field (pf) and the boost queries.
     *
     * @param int $fieldCount number of qf fields
     * @param int $maxClauses Solr's maxBooleanClauses
     * @return int at least 1
     */
    public static function wordBudget( $fieldCount, $maxClauses = self::DEFAULT_MAX_CLAUSES )
    {
        $fieldCount = max( 1, (int) $fieldCount );
        $maxClauses = (int) $maxClauses > 0 ? (int) $maxClauses : self::DEFAULT_MAX_CLAUSES;
        return max( 1, intdiv( $maxClauses, $fieldCount + 1 ) );
    }

    /**
     * The text cut to the words the clause budget allows, the first ones kept.
     *
     * @param string $text
     * @param int $fieldCount number of qf fields
     * @param int $maxClauses Solr's maxBooleanClauses
     * @return array{0: string, 1: bool} the text, and whether it was shortened
     */
    public static function limitToClauseBudget( $text, $fieldCount, $maxClauses = self::DEFAULT_MAX_CLAUSES )
    {
        $units = self::units( $text );
        $budget = self::wordBudget( $fieldCount, $maxClauses );
        if ( count( $units ) <= $budget )
        {
            return array( (string) $text, false );
        }
        return array( implode( ' ', array_slice( $units, 0, $budget ) ), true );
    }

    /**
     * The text without the query syntax a typed search does not mean:
     *  - an unmatched double quote is dropped (Solr found nothing for
     *    "unbalanced, without an error);
     *  - outside quotes, * and ? are escaped unless wildcards are enabled;
     *  - a ? that ends a word is always escaped: it ends a question.
     * Phrases, +word, -word, AND/OR/NOT and word~ stay as typed.
     *
     * @param string $text
     * @param bool $enableWildcard site.ini [SearchSettings] EnableWildcard=true
     * @return string
     */
    public static function escapeSyntax( $text, $enableWildcard )
    {
        $text = (string) $text;
        if ( substr_count( $text, '"' ) % 2 === 1 )
        {
            $last = strrpos( $text, '"' );
            $text = substr( $text, 0, $last ) . substr( $text, $last + 1 );
        }

        $parts = preg_split( '/("[^"]*")/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
        foreach ( $parts as $index => $part )
        {
            if ( $index % 2 === 1 )
            {
                continue; // a phrase: Solr does not read wildcards in it
            }
            $parts[$index] = self::escapeWildcards( $part, $enableWildcard );
        }
        return implode( '', $parts );
    }

    /**
     * escapeSyntax() for a text without quotes. An escaped character (\x)
     * stays as it is.
     */
    private static function escapeWildcards( $part, $enableWildcard )
    {
        $result = '';
        $length = strlen( $part );
        for ( $i = 0; $i < $length; $i++ )
        {
            $char = $part[$i];
            if ( $char === '\\' && $i + 1 < $length )
            {
                $result .= $char . $part[++$i];
                continue;
            }
            if ( $char === '*' || $char === '?' )
            {
                $endsWord = $i + 1 >= $length || ctype_space( $part[$i + 1] );
                if ( !$enableWildcard || ( $char === '?' && $endsWord ) )
                {
                    $result .= '\\' . $char;
                    continue;
                }
            }
            $result .= $char;
        }
        return $result;
    }
}
