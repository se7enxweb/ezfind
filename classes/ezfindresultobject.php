<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

class eZFindResultObject extends eZContentObject
{
    /*!
     \reimp
    */
    function __construct( $rows = array() )
    {
        // eZContentObject::__construct() is not called, as it never was: a result object has only its local
        // attributes, and the rows of a search result are no rows of the ezcontentobject table
        $this->LocalAttributeValueList = array();
        $this->LocalAttributeNameList = array( 'published' );

        foreach ( $rows as $name => $value )
        {
            $this->setAttribute( $name, $value );
        }
    }

    /**
     * The constructor under its old name, which PHP 8 no longer calls on "new"; kept for code that calls it.
     *
     * @deprecated Use the constructor
     * @param array $rows
     */
    function eZFindResultObject( $rows = array() )
    {
        $this->__construct( $rows );
    }

    /*!
     \reimp
    */
    function attribute( $attr, $noFunction = false )
    {
        return isset( $this->LocalAttributeValueList[$attr] ) ?
                $this->LocalAttributeValueList[$attr] : null;
    }

    /*!
     \reimp
    */
    function setAttribute( $attr, $value )
    {
        if ( in_array( $attr, $this->LocalAttributeNameList ) )
        {
            $this->LocalAttributeValueList[$attr] = $value;
        }
    }

    /*!
     \reimp
    */
    function attributes()
    {
        return array_merge( $this->LocalAttributeNameList,
                            eZContentObject::attributes() );
    }

    /*!
     \reimp
    */
    function hasAttribute( $attr )
    {
        return ( in_array( $attr, $this->LocalAttributeNameList ) ||
                 eZContentObject::hasAttribute( $attr ) );
    }


    /// Object variables
    var $LocalAttributeValueList;
    var $LocalAttributeNameList;
}

?>
