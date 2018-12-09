<?php
namespace Cartography\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AdapterInterface;

/**
 * This trait must be used inside an adapter, because there are calls to the
 * adapter methods.
 * Nevertheless, the second method is used in Module too.
 */
trait QueryGeometryTrait
{
    /**
     * Build query on geometry (coordinates, box, wkt).
     *
     * @todo Add a filter by property (search in all properties currently).
     * @todo Allow to have multiple arguments (via a check of the first key: integer or string).
     * @todo Manage another operator than within.
     *
     * @see \Cartography\View\Helper\NormalizeGeometryQuery for the format.
     *
     * Warning: the sql functions are the mariaDB/mySql ones, not the PostgreSql
     * ones. For example, MBRContains is not supported by PostgreSql and a
     * Doctrine type should be added.
     * @todo Convert queries or add types to support PostgreSql.
     *
     * @link https://stackoverflow.com/questions/21670198/sql-geometry-find-all-points-in-a-radius
     *
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function searchGeometry(AdapterInterface $adapter, QueryBuilder $qb, array $query)
    {
        $normalizeGeometryQuery = $adapter->getServiceLocator()->get('ViewHelperManager')->get('normalizeGeometryQuery');
        $query = $normalizeGeometryQuery($query);
        if (empty($query['geo'])) {
            return;
        }
        $geos = $query['geo'];
        $first = reset($geos);
        $isSingle = !is_array($first) || !is_numeric(key($geos));
        if ($isSingle) {
            $geos = [$geos];
        }
        $geometryAlias = $this->joinGeometry($adapter, $qb, $query);

        foreach ($geos as $geo) {
            if (array_key_exists('latlong', $geo) && array_key_exists('radius', $geo)) {
                $this->searchLatLong($adapter, $qb, $geo['latlong'], $geo['radius'], $geo['unit'], $geometryAlias);
            } elseif (array_key_exists('xy', $geo) && array_key_exists('radius', $geo)) {
                $this->searchXy($adapter, $qb, $geo['xy'], $geo['radius'], $geometryAlias);
            } elseif (array_key_exists('mapbox', $geo)) {
                $this->searchMapBox($adapter, $qb, $geo['mapbox'], $geometryAlias);
            } elseif (array_key_exists('box', $geo)) {
                $this->searchBox($adapter, $qb, $geo['box'], $geometryAlias);
            } elseif (array_key_exists('wkt', $geo)) {
                $this->searchWkt($adapter, $qb, $geo['wkt'], $geometryAlias);
            }
        }
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $latlong
     * @param double $radius
     * @param string $unit
     * @param string|int|null $geometryAlias
     */
    protected function searchLatLong(AdapterInterface $adapter, QueryBuilder $qb, array $latlong, $radius, $unit, $geometryAlias)
    {
        // With srid 4326 (Mercator), the radius should be in metre.
        $radiusMetre = $unit === 'm' ? $radius : $radius * 1000;
        $point = vsprintf('Point(%s, %s)', array_reverse($latlong));
        $qb->andWhere($qb->expr()->lte(
            "ST_Distance($point, $geometryAlias.value)",
            $adapter->createNamedParameter($qb, $radiusMetre)
        ));
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $xy
     * @param double $radius
     * @param string|int|null $geometryAlias
     */
    protected function searchXy(AdapterInterface $adapter, QueryBuilder $qb, array $xy, $radius, $geometryAlias)
    {
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $mapbox
     * @param string|int|null $geometryAlias
     */
    protected function searchMapBox(AdapterInterface $adapter, QueryBuilder $qb, array $mapbox, $geometryAlias)
    {
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $box
     * @param string|int|null $geometryAlias
     */
    protected function searchBox(AdapterInterface $adapter, QueryBuilder $qb, array $box, $geometryAlias)
    {
    }

    /**
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param string $wkt
     * @param string|int|null $geometryAlias
     */
    protected function searchWkt(AdapterInterface $adapter, QueryBuilder $qb, $wkt, $geometryAlias)
    {
    }

    /**
     * Join the geometry table.
     *
     * @param AdapterInterface $adapter
     * @param QueryBuilder $qb
     * @param array $query
     * @return string Alias used for the geometry table.
     */
    protected function joinGeometry(AdapterInterface $adapter, QueryBuilder $qb, $query)
    {
        $resourceClass = $adapter->getEntityClass();
        $dataTypeClass = \Cartography\Entity\DataTypeGeometry::class;
        $alias = $adapter->createAlias();
        $property = isset($query['geo']['property']) ? $query['geo']['property'] : null;
        if ($property) {
            $propertyId = $this->getPropertyId($adapter, $property);
            $expr = $qb->expr();
            $qb->join(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $qb->expr()->andX(
                    $expr->eq($alias . '.resource', $resourceClass . '.id'),
                    $expr->eq($alias . '.property', $propertyId)
                )
            );
        } else {
            $qb->join(
                $dataTypeClass,
                $alias,
                \Doctrine\ORM\Query\Expr\Join::WITH,
                $qb->expr()->eq($alias . '.resource', $resourceClass . '.id')
            );
        }
        return $alias;
    }

    /**
     * Get a property id from a property term or an integer.
     *
     * @param AdapterInterface $adapter
     * @param string|int property
     * @return int
     */
    protected function getPropertyId(AdapterInterface $adapter, $property)
    {
        if (empty($property)) {
            return 0;
        }
        if (is_numeric($property)) {
            return (int) $property;
        }
        if (!preg_match('/^[a-z0-9-_]+:[a-z0-9-_]+$/i', $property)) {
            return 0;
        }
        list($prefix, $localName) = explode(':', $property);
        $dql = <<<'DQL'
SELECT p.id
FROM Omeka\Entity\Property p
JOIN p.vocabulary v
WHERE p.localName = :localName
AND v.prefix = :prefix
DQL;
        return (int) $adapter
            ->getEntityManager()
            ->createQuery($dql)
            ->setParameters([
                'localName' => $localName,
                'prefix' => $prefix,
            ])
            ->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);
    }
}
