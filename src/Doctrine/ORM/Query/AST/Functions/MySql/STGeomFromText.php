<?php
namespace Cartography\Doctrine\ORM\Query\AST\Functions\MySql;

use CrEOF\Spatial\ORM\Query\AST\Functions\AbstractSpatialDQLFunction;

/**
 * ST_GeomFromText DQL function
 */
class STGeomFromText extends AbstractSpatialDQLFunction
{
    protected $platforms = array('mysql');

    protected $functionName = 'ST_GeomFromText';

    protected $minGeomExpr = 1;

    protected $maxGeomExpr = 2;
}
