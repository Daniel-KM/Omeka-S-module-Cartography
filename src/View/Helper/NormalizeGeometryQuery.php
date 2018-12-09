<?php
namespace Cartography\View\Helper;

use CrEOF\Geo\WKT\Parser as GeoWktParser;
use Doctrine\ORM\EntityManager;
use Zend\View\Helper\AbstractHelper;

class NormalizeGeometryQuery extends AbstractHelper
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Normalize a query for geometries (latlong, xy, radius, box and wkt).
     *
     * The arguments are exclusive, so multiple args are removed to keep only
     * the first good one (point, then box, then wkt).
     * When strings are used, the separator can be anything except ".".
     * The regular format is the first one.
     * The input can be single a single query or a list of query.
     * It can be used for front-end form or back-end api.
     *
     * Geometry (for flat image):
     * [geo][xy] = [x, y] or "x y"
     * [geo][radius] = radius
     * [geo][box] = [left x, top y, right x, bottom y] or [[left, top], [right, bottom]] or string "x1 y1 x2 y2"
     *
     * Geographic (for georeferenced data):
     * [geo][latlong] = [lat, long] or 'lat long'
     * [geo][radius] = radius
     * [geo][unit] = 'km' (default) or 'm' (1 km = 1000 m)
     * [geo][mapbox] = [top lat, left long, bottom lat, right lat] or [[top, left], [bottom, right]] or string "lat1 long1 lat2 long2"
     * @todo Query / set database srid (useless for geometry, can be a default for geographic according to radius)?
     * [geo][srid] =  Spatial Reference Identifier
     *
     * Common for geometry and geography:
     * [geo][wkt] = 'wkt'
     * [geo][property] = 'dcterms:spatial' or another one (term or id)
     *
     * @param array $query
     * @return array The cleaned query.
     */
    public function __invoke($query)
    {
        if (empty($query['geo'])) {
            return $query;
        }

        $first = reset($query['geo']);
        $isSingle = !is_array($first) || !is_numeric(key($first));
        if ($isSingle) {
            $query['geo'] = [$query['geo']];
        }

        $defaults = [
            // Geographic coordinates as latitude and longitude.
            'latlong' => null,
            // Geometric coordinates as x and y.
            'xy' => null,
            // A float.
            'radius' => null,
            // Unit only for latlong radius. Can be km 'default) or m. Always pixels for xy.
            'unit' => null,
            // Two opposite geographic coordinates (latlong).
            'mapbox' => null,
            // Two opposite geometric coordinates (xy).
            'box' => null,
            // A well-known text.
            'wkt' => null,
            // Property (data type should be "geometry"), or search all properties.
            'property' => null,
        ];

        foreach ($query['geo'] as $key => &$geo) {
            $result = [];
            $geo += $defaults;

            $property = $this->getPropertyId($geo['property']);
            if (is_int($property)) {
                $result['property'] = $property;
            }

            if ($geo['latlong'] && $geo['radius']) {
                $latlong = $this->normalizeLatLong($geo['latlong']);
                $radius = $this->normalizeMapRadius($geo['radius']);
                if ($latlong && $radius) {
                    $geo = $result;
                    $geo['latlong'] = $latlong;
                    $geo['radius'] = $radius;
                    $geo['unit'] = isset($geo['unit']) && in_array($geo['unit'], ['km', 'm'])
                        ? $geo['unit']
                        : 'km';
                    continue;
                }
            }

            if ($geo['xy'] && $geo['radius']) {
                $xy = $this->normalizeXy($geo['xy']);
                $radius  = $this->normalizeRadius($geo['radius']);
                if ($xy && $radius) {
                    $geo = $result;
                    $geo['xy'] = $xy;
                    $geo['radius'] = $radius;
                    continue;
                }
            }

            if ($geo['mapbox']) {
                $mapbox = $this->normalizeMapBox($geo['mapbox']);
                if ($mapbox) {
                    $geo = $result;
                    $geo['mapbox'] = $mapbox;
                    continue;
                }
            }

            if ($geo['box']) {
                $box = $this->normalizeBox($geo['box']);
                if ($box) {
                    $geo = $result;
                    $geo['box'] = $box;
                    continue;
                }
            }

            if ($geo['wkt']) {
                $wkt = $this->normalizeWkt($geo['wkt']);
                if ($wkt) {
                    $geo = $result;
                    $geo ['wkt'] = $wkt;
                    continue;
                }
            }

            unset($query['geo'][$key]);
        }
        unset($geo);

        if (empty($query['geo'])) {
            unset($query['geo']);
        } elseif ($isSingle) {
            $query['geo'] = reset($query['geo']);
        }
        return $query;
    }

    protected function normalizeLatLong($latlong)
    {
        $latlong = $this->normalizeXy($latlong);
        if ($latlong) {
            $latitude = $latlong[0];
            $longitude = $latlong[1];
            // Until 18 digits.
            // $regexLatitude = '/^(-?[1-8]?\d(?:\.\d{1,18})?|90(?:\.0{1,18})?)$/';
            // $regexLongitude = '/^(-?(?:1[0-7]|[1-9])?\d(?:\.\d{1,18})?|180(?:\.0{1,18})?)$/';
            // if (preg_match($regexLatitude, $latitude) && preg_match($regexLongitude, $longitude)) {
            if ($latitude >= -90 && $latitude <= 90
                && $longitude >= -180 && $longitude <= 180
            ) {
                return $latlong;
            }
        }
    }

    protected function normalizeXy($xy)
    {
        if (is_array($xy)) {
            if (count($xy) !== 2) {
                return;
            }
            $x = $xy[0];
            $y = $xy[1];
        } else {
            $xy = preg_replace('[^0-9.]', ' ', $xy);
            $xy = preg_replace('/\s+/', ' ', trim($xy));
            if (strpos($xy, ' ') === false) {
                return;
            }
            list($x, $y) = explode(' ', $xy, 2);
        }
        if (is_numeric($x) && is_numeric($y)) {
            return [$x, $y];
        }
    }

    protected function normalizeMapRadius($radius)
    {
        $radius = trim($radius);
        if ($radius > 0 && $radius < 20038) {
            return $radius;
        }
    }

    protected function normalizeRadius($radius)
    {
        $radius = trim($radius);
        if ($radius > 0) {
            return $radius;
        }
    }

    protected function normalizeMapBox($mapbox)
    {
        $mapbox = $this->normalizeBox($mapbox);
        if ($mapbox) {
            $top = $mapbox[0];
            $left = $mapbox[1];
            $bottom = $mapbox[2];
            $right = $mapbox[3];
            if ($top >= -90 && $top <= 90
                && $left >= -180 && $left <= 180
                && $bottom >= -90 && $bottom <= 90
                && $right >= -180 && $right <= 180
            ) {
                return $mapbox;
            }
        }
    }

    protected function normalizeBox($box)
    {
        if (is_array($box)) {
            if (count($box) === 4) {
                $left = $box[0];
                $top = $box[1];
                $right = $box[2];
                $bottom = $box[3];
            } elseif (count($box) !== 2 || count($box[0]) !== 2 || count($box[1]) !== 2) {
                return;
            } else {
                $left = $box[0][0];
                $top = $box[0][1];
                $right = $box[1][0];
                $bottom = $box[1][1];
            }
        } else {
            $box = preg_replace('[^0-9.]', ' ', $box);
            $box = preg_replace('/\s+/', ' ', trim($box));
            if (strpos($box, ' ') === false) {
                return;
            }
            list($left, $top, $right, $bottom) = explode(' ', $box, 4);
        }
        if (is_numeric($left)
            && is_numeric($top)
            && is_numeric($right)
            && is_numeric($bottom)
            && $left != $right
            && $top != $bottom
        ) {
            return [$left, $top, $right, $bottom];
        }
    }

    protected function normalizeWkt($wkt)
    {
        $wkt = trim($wkt);
        try {
            $geometry = new GeoWktParser($wkt);
            $geometry = $geometry->parse();
        } catch (\Exception $e) {
            return;
        }
        return $wkt;
    }

    /**
     * Get a property id from a property term or an integer.
     *
     * @param string|int property
     * @return int
     */
    protected function getPropertyId($property)
    {
        static $properties = [];

        if (is_null($property) || $property === '') {
            return;
        }

        if (isset($properties[$property])) {
            return $properties[$property];
        }

        if (empty($property)) {
            return 0;
        }

        if (is_numeric($property)) {
            $property = (int) $property;
            $properties[$property] = $property;
            return (int) $property;
        }

        if (!preg_match('/^[a-z0-9-_]+:[a-z0-9-_]+$/i', $property)) {
            $properties[$property] = 0;
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
        $properties[$property] = (int) $this->entityManager
            ->createQuery($dql)
            ->setParameters([
                'localName' => $localName,
                'prefix' => $prefix,
            ])
            ->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_SINGLE_SCALAR);
        return $properties[$property];
    }
}
