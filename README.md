Cartography: Annotate images and maps (module for Omeka S)
==========================================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Cartography] is a module for [Omeka S] that allows to annotate (to draw points,
lines, polylines, polygons, etc.) an image or a map with the [web annotation vocabulary]
and the [web annotation data model].

Maps can be georeferenced ([wms]) or ungeoreferenced images, so it is possible
to annotate any images, even non-cartographic ones.


Installation
------------

This module requires the modules [Annotate] and [Data Type Geometry].
If the module [Generic] is used, it must be version greater or equal to 3.4.41.

The module uses external libraries, so use the release zip to install it, or use
and init the source.

See general end user documentation for [installing a module].

* From the zip

Download the last release [`Cartography.zip`] from the list of releases (the
master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Cartography`, go to the root of the module, and run:

```sh
npm install
composer install --no-dev
gulp
```

* Database

See notes in the module [Data Type Geometry].


Quick start
-----------

The module allows to annotate standard images and georeferenced maps.
- To annotate a still image, simply upload it as media.
- To annotate a georeferenced image, the [wms] url should be set as URI in
  `dcterms:spatial`, for example: `https://mapwarper.net/maps/wms/12428` with
  label `London Hogenberg 1572`. There can be multiple [wms] layers, for example
  you can add the `Roads to Santiago de Compostela in Spain` too with the url
  `https://www.ign.es/wms-inspire/camino-santiago?layers=PS.ProtectedSite`.

Then, simply open the item (the display view, not the edition view, because the
annotations are saved outside of the resource metadata), go to the tabs
`Describe` or `Locate`, then annotate.

There may be multiple images and georeferenced maps. Furthermore, the upper maps
are displayed too: the item sets wms layers are displayed with the item one, and
the item ones with the media one.

Notes
- A geometry can have only one description, because there is only one popup. So
  only one body is managed, except when there are links.
- According to the Annotation data model, when a geometry is linked to a
  resource, the motivation is forced to "linking". Furthermore, the annotation
  body is the related resource and the target is the geometry, like all other
  motivations.
  Nevertheless, a description can be appended too, with a second motivation. In
  that case, the description describes the target, like always, so it is not a
  description of the links.


Configuration
-------------

### Forms

The images and the maps can be described with any metadata. These metadata are
listed in a standard Òmeka resource template, but it must have the class
`oa:Annotation`.

The images and the maps can be described with any metadata. Nevertheless, it is
recommended to use standard annotations ones, in particular `rdf:value` for the
body (equivalent to `dcterms:description` for the resources) and `oa:motivatedBy`
for annotation itself (to simplify the search). The relations with other
resources (for example identification of an object on an image is an image of
another item) should use the element `oa:hasBody`.

Note that according to the [Web Annotation data model], an annotation is divided
in three part: the annoation itself, one or more target (only one with this
module) and zero or more bodies (a description, a link, etc.). So the properties
of the annoations should be attached to one of this part via the resource
template, so they will be saved regularly. It's particularly important for the
non-standard metadata (the default one are automapped according to the data
model, when possible). The default automapping is set in a [config file] of the
module [Annotate] and can be updated.

There may be one or multiple forms for each tab (Describe and Locate), for
example one to describe object, another one to identify people, and another one
to describe place. They are selected in the main settings of Omeka S.

### Ontologies

The annotations use the [web annotation vocabulary].

#### Locate georeferenced images

The base maps of the tab "Locate" can be customized. Just set it as js in the
config. For example, to include the French [IGN map service] with the default
map, fill the text area with the code below.

```js
/**
 * Define the layer type:
 * - GEOGRAPHICALGRIDSYSTEMS.MAPS
 * - GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN-EXPRESS.CLASSIQUE
 * - GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN-EXPRESS.STANDARD
 *
 * @url https://geoservices.ign.fr
 */
var layerIGNScanStd = 'GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN-EXPRESS.STANDARD';
// For test only. Get a free key for production on https://geoservices.ign.fr/blog/2018/09/06/acces_geoportail_sans_compte.html.
var ignKey = 'choisirgeoportail';
var url = 'https://wxs.ign.fr/' + ignKey + '/geoportail/wmts';
var ign = new L.TileLayer.WMTS(url, {
    layer: layerIGNScanStd,
    style: 'normal',
    tilematrixSet: 'PM',
    format: 'image/jpeg',
    attribution: '&copy; <a href="https://www.ign.fr">IGN</a>',
});

baseMaps = {
    'Streets': L.tileLayer.provider('OpenStreetMap.Mapnik'),
    'Satellite': L.tileLayer.provider('Esri.WorldImagery'),
    'Terrain': L.tileLayer.provider('Esri.WorldShadedRelief'),
    'IGN': ign,
};
```

Note: the key provided here for access to IGN services must be used for test
purpose only. In production, you must register to get a [free ign key].

#### Describe still images

A similar js can be added for images to describe, but is not managed directly by
the module currently.

### Geometries

The geometries are saved as standard Omeka values and indexed in a specific
table with a spatial index for quick search via the module [Data Type Geometry].

**Warning**: The geometry "Circle" is not managed by WKT or by GeoJSON, but only
by the viewer. It is saved as a `POINT` in the database, without radius.

### JSON-LD and GeoJSON

According to the [discussion] on the working group of JSON-LD and GeoJSON, the
GeoJson cannot be used in all cases inside a JSON-LD. So the representation uses
the data type `http://www.opengis.net/ont/geosparql#wktLiteral` of the [OGC standard].
The deprecated datatype `http://geovocab.org/geometry#asWKT` is no more used.

```json
{
    "@value": "POINT (2.294497 48.858252)",
    "@type": "http://www.opengis.net/ont/geosparql#wktLiteral"
}
```


TODO
----

- [ ] Add a configurable list of styles in the style editor (or replace the fields used to edit styles).
- [ ] Add the specific config of the wmts at the site level.
- [ ] Omeka S v4: fix link resource.
- [ ] Fix display of data in popup.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.

### Libraries

This module uses many open source leaflet libraries. See [asset/vendor] for
details.


Copyright
---------

* See `asset/vendor/` and `vendor/` for the copyright of the libraries.
* Some portions are adapterd from the modules [Numeric data types] and [Neatline].
* Copyright Daniel Berthereau, 2018, (see [Daniel-KM] on GitLab)

This module was built first for the French École des hautes études en sciences
sociales [EHESS]. The maintenance was done for [INHA].


[Cartography]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cartography
[Omeka S]: https://omeka.org/s
[web annotation data model]: https://www.w3.org/TR/annotation-model/
[web annotation vocabulary]: https://www.w3.org/TR/annotation-vocab/
[wms]: https://en.wikipedia.org/wiki/Web_Map_Service
[Annotate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate
[Data Type Geometry]: https://gitlab.com/Daniel-KM/Omeka-S-module-DataTypeGeometry
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[MariaDB 10.2.2]: https://mariadb.com/kb/en/library/spatial-index/
[mySql 5.7.5]: https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-5.html#mysqld-5-7-5-innodb
[mySql 5.5.3]: https://dev.mysql.com/doc/relnotes/mysql/5.5/en/news-5-5-3.html
[MariaDB 5.5.20]: https://mariadb.com/kb/en/library/mariadb-5521-release-notes/
[mySql 5.6.1]: https://dev.mysql.com/doc/relnotes/mysql/5.6/en/news-5-6-1.html
[MariaDB 5.3.3]: https://mariadb.com/kb/en/library/mariadb-533-release-notes/
[spatial support matrix]: https://mariadb.com/kb/en/library/mysqlmariadb-spatial-support-matrix/
[`data_type_geometry`]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cartography/-/tree/master/data/install/schema.sql
[doctrine2-spatial]: https://github.com/creof/doctrine2-spatial/blob/HEAD/doc/index.md
[`Cartography.zip`]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cartography/-/releases
[Web Annotation data model]: https://www.w3.org/TR/annotation-model/#introduction
[config file]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate/-/tree/master/data/mappings/properties_to_annotation_parts.php
[Annotate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate
[IGN map service]: https://geoservices.ign.fr
[free ign key]: https://geoservices.ign.fr/blog/2018/09/06/acces_geoportail_sans_compte.html
[discussion]: https://github.com/json-ld/json-ld.org/issues/397
[`geo:asWKT`]: http://geovocab.org/geometry#asWKT
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cartography/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Numeric data types]: https://github.com/omeka-s-modules/NumericDataTypes
[Neatline]: https://github.com/performant-software/neatline-omeka-s
[EHESS]: https://www.ehess.fr
[INHA]: https://www.inha.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
