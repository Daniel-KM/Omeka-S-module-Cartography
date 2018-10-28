Cartography annotate (module for Omeka S)
=========================================

[Cartography] is a module for [Omeka S] that allows to annotate (to draw points,
lines, polylines, polygons, etc.) an image or a map with the [web annotation vocabulary]
and the [web annotation data model].

Maps can be georeferenced ([wms]) or ungeoreferenced images, so it is possible
to annotate any images, even not cartagraphic ones.

**This is a work in progress (ALPHA release).**


Installation
------------

This module requires the modules [Annotate].

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

```
    npm install
    gulp
```


Quick start
-----------

The module allows to annotate standard images and georeferenced maps.
- To annotate a still image, simply upload it as media.
- To annotate a georeferenced image, the [wms] url should be set as URI in
  `dcterms:spatial`, for example: `https://mapwarper.net/maps/wms/14068?` with
  label `Us and territories`. There can be multiple [wms] layers, for example
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

### List of terms

The list of terms for the motivation, the purpose and the uncertainty can be
changed via the module `Custom vocab`. The title of the vocabularies must be
kept as they are, because they are used internally.


### Ontologies

The annotations uses the [web annotation vocabulary].

The class `ogc:WKTLiteral` and the property `geo:asWKT` of the ontologies of the
[Open Geospatial consortium] are used. They may be converted into the [geovocab]
vocabulary (`ngeo:Geometry`) if needed.


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
    'Grayscale': L.tileLayer.provider('OpenStreetMap.BlackAndWhite'),
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


TODO
----

- Make the list of the fields of the style editor configurable.
- Add a configurable list of styles in the style editor (or replace the fields
  used to edit styles).
- Add the specific config of the wmts at the site level.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

This module is published under the [CeCILL v2.1] licence, compatible with
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


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel-KM] on GitHub) for EHESS.


Copyright
---------

* Copyright Daniel Berthereau, 2018


[Cartography]: https://github.com/Daniel-KM/Omeka-S-module-Cartography
[Omeka S]: https://omeka.org/s
[web annotation data model]: https://www.w3.org/TR/annotation-model/
[web annotation vocabulary]: https://www.w3.org/TR/annotation-vocab/
[wms]: https://en.wikipedia.org/wiki/Web_Map_Service
[Annotate]: https://github.com/Daniel-KM/Omeka-S-module-Annotate
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[`Cartography.zip`]: https://github.com/Daniel-KM/Omeka-S-module-Cartography/releases
[Open Geospatial consortium]: http://www.opengeospatial.org/
[geovocab]: http://geovocab.org/
[IGN map service]: https://geoservices.ign.fr
[free ign key]: https://geoservices.ign.fr/blog/2018/09/06/acces_geoportail_sans_compte.html
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-Cartography/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[asset/vendor]: https://github.com/Daniel-KM/Omeka-S-module-Cartography/tree/master/asset/vendor
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
