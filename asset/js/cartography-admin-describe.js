/* Cartography */

// TODO Merge this file with cartography-admin (currently only some variables are different).

// The base code comes initially from Mapping/asset/js/mapping-show.js in order
// to keep standard markers., but now the dependency has been removed.
// TODO Check if some code is dead or useless.
// TODO The automatic bounds should be managed to fit existing geometries (or let the user set it and store it like in mapping?) or full map.
// TODO Clarify use of the annotation identifier.

$(document).ready( function() {

/**
 * Fetch geometries for a resource.
 *
 * @return array
 */
var fetchGeometries = function(identifier, partIdentifier) {
    var url = basePath + '/admin/cartography/' + identifier + '/geometries'
        + '?mediaId=' + (partIdentifier ? partIdentifier : '-1');

    $.get(url)
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            displayGeometries(data.geometries);
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the geometries.');
            alert(message);
        });
}

/**
 * Display geometries.
 */
var displayGeometries = function(geometries) {
    $.each(geometries, function(index, data) {
        var layer;
        var geojson = Terraformer.WKT.parse(data['wkt']);
        var options = data['options'] || {};
        options.annotationIdentifier = data['id'];

        // Prepare to set the content of the popup.
        if (options.popupContent) {
            options.onEachFeature = function(feature, layer) {
                layer.bindPopup(options.popupContent);

                // To reserve the options from geoJson.
                layer.options = layer.options || {};
                // To prepare for style editor form-element initial value.
                layer.options = $.extend(options, layer.options);
            }
        }

        // Prepare the layer.
        if (geojson.type === 'Point' && typeof options.radius !== 'undefined') {
            // Warning: the coordinates are inversed on an image.
            layer = L.circle([geojson.coordinates[1], geojson.coordinates[0]], options);

            // Set the content of the popup.
            if (layer && options.popupContent) {
                layer.bindPopup(options.popupContent);
            }
        } else {
            layer = L.geoJson(geojson, options);
        }

        // Append the geometry to the map.
        addGeometry(layer, data['id']);

        // TODO Remove one of the two places where the annotation identifier is saved.
        layer.annotationIdentifier = data['id'];
        layer.options.annotationIdentifier = data['id'];
    });
}

/**
 * Add a geometry to the map.
 *
 * @param layer
 * @param identifier
 */
var addGeometry = function(layer, identifier) {
    // Don't save the geometry two times.
    if (identifier) {
        layer.annotationIdentifier = identifier;
        layer.options.annotationIdentifier = identifier;
        // drawnItems.addLayer(layer);
        addNonGroupLayers(layer, drawnItems);
        layer.annotationIdentifier = identifier;
        layer.options.annotationIdentifier = identifier;
        return;
    }

    var geojson = layer.toGeoJSON();
    var wkt;
    // Check the process of terraformer wkt convert / reconvert (Feature/FeatureCollection).
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.WKT.convert(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.WKT.convert(geojson.geometry);
    }

    // Options are saved only when updated: some people don't need styles so it
    // will be lighter in that case. Except for circle, that has the required
    // option radius.
    var options = typeof layer.getRadius === 'function' ? {'radius': layer.getRadius()} : {};

    var url = basePath + '/admin/cartography/annotate';
    var data = {
        // Identifier is always empty.
        id : identifier,
        resourceId: resourceId,
        mediaId: currentMediaId(),
        wkt: wkt,
        options: options,
    };

    $.post(url, data)
        .done(function(data) {
            // No json means error, and the only non-json error is redirect to login.
            if (!data.result) {
                alert(Omeka.jsTranslate('Log in to save the geometry.'));
                return;
            }
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            identifier = data.result.id;
            layer.annotationIdentifier = identifier;
            layer.options.annotationIdentifier = identifier;
            drawnItems.addLayer(layer);
            console.log('Geometry added.');
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to save the geometry.');
            alert(message);
            // The deletion is automatic when not recorded.
        });
};

/**
 * Edit a geometry.
 *
 * @param layer
 */
var editGeometry = function(layer) {
    var identifier = layer.annotationIdentifier || getMarkerIdentifier(layer);
    if (!identifier) {
        alert(Omeka.jsTranslate('Unable to save the edited geometry.'));
        return;
    }

    var geojson = layer.toGeoJSON();
    var wkt;
    // Check the process of terraformer wkt convert / reconvert (Feature/FeatureCollection).
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.WKT.convert(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.WKT.convert(geojson.geometry);
    }

    // Options radius should be set here, because it is updated automatically.
    if (typeof layer.getRadius === 'function') {
        layer.options.radius = layer.getRadius();
    }

    var url = basePath + '/admin/cartography/annotate';
    var data = {
        id: identifier,
        // TODO Remove the media id, since it cannot change (currently needed in controller).
        mediaId: currentMediaId(),
        wkt: wkt,
        options: layer.options
    };

    // Clean the post data (this should not be needed).
    buildParams(data);

    $.post(url, data)
        .done(function(data) {
            // No json means error, and the only non-json error is redirect to login.
            if (!data.result) {
                alert(Omeka.jsTranslate('Log in to edit the geometry.'));
                return;
            }
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            console.log('Geometry updated.');
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to update the geometry.');
            alert(message);
        });
}

/**
 * Delete a geometry.
 *
 * @param layer
 */
var deleteGeometry = function(layer) {
    var url = basePath + '/admin/cartography/delete-annotation';
    var identifier = layer.annotationIdentifier || getMarkerIdentifier(layer);
    if (!identifier) {
        console.log('No identifier to delete.');
        return;
    }
    var data = {id: identifier};

    $.post(url, data)
        .done(function(data) {
            // No json means error, and the only non-json error is redirect to login.
            if (!data.result) {
                alert(Omeka.jsTranslate('Log in to delete the geometry.'));
                return;
            }
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            console.log('Geometry deleted.');
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to delete the geometry.');
            alert(message);
        });
}

/**
 * Allows to create editable layer from existing geometries.
 *
 * @see https://gis.stackexchange.com/questions/203540/how-to-edit-an-existing-layer-using-leaflet/203773#203773
 * @todo Fix: the existing groups are not draggable (but the newly created were).
 */
var addNonGroupLayers = function(sourceLayer, targetGroup) {
    if (sourceLayer instanceof L.LayerGroup) {
        sourceLayer.eachLayer(function (layer) {
            addNonGroupLayers(layer, targetGroup);
        });
    } else {
        targetGroup.addLayer(sourceLayer);
        sourceLayer.annotationIdentifier = sourceLayer.options.annotationIdentifier;
    }
}

/**
 * Get marker identifier.
 *
 * @todo Fix this process, too hacky: the identifier should be simple to save and find.
 * @param layer
 * @return int
 */
var getMarkerIdentifier = function(layer) {
    var identifier = layer.options.annotationIdentifier;
    if (identifier) {
        return identifier;
    }
    var parents = Object.values(layer._eventParents);
    return parents[parents.length - 1].annotationIdentifier;
}

/**
 * Get the media id of the current image overlay.
 *
 * @todo Get id of the current ImageOverlay via Leaflet methods, not jQuery.
 * @return int
 */
var currentMediaId = function() {
    var mediaId = $('#cartography-media').find('.leaflet-control-layers input[name="leaflet-base-layers"]:checked').next('span').text();
    mediaId = mediaId.length < 1 ? 1 : mediaId.substring(mediaId.lastIndexOf('#') + 1).trim();
    mediaId = mainImages[mediaId - 1].id;
    return mediaId;
}

/**
 * Fit map bounds.
 *
 * @todo Fit map bounds.
 */
var setView = function() {
    var bounds;
    // Fit bounds from the current image layer.
};

/* Initialization */

var section = 'describe';

// TODO Find the way to get the current annotation after the resource selection.
var currentAnnotation;

// Initialize the map and set default view.
var map = L.map('cartography-media', {
    // TODO Compute the min/max zoom according to images?
    minZoom: -4,
    maxZoom: 8,
    zoom: 0,
    center: [0, 0],
    maxBoundsViscosity: 1,
    crs: L.CRS.Simple,
    pasteControl: true,
});
map.setView([0, 0], 0);

var mapMoved = false;

//Add layers and controls to the map.
var baseMaps = {};
var firstMap;
$.each(mainImages, function(index, mainImage) {
    // Compute image edges as positive coordinates.
    // TODO Choose top left as 0.0 for still images?
    var southWest = L.latLng(0, 0);
    var northEast = L.latLng(mainImage.size[1], mainImage.size[0]);
    var bounds = L.latLngBounds(southWest, northEast);
    var image = L.imageOverlay(mainImage.url, bounds);
    if (!firstMap) {
        firstMap = image;
    }
    baseMaps[Omeka.jsTranslate('Image #') + (index + 1)] = image;
});
if (Object.keys(baseMaps).length > 1) {
    var layerControl = L.control.layers(baseMaps);
    map.addControl(new L.Control.Layers(baseMaps));
}
var bounds = firstMap.getBounds();
firstMap.addTo(map);
map.panTo([bounds.getNorthEast().lat / 2, bounds.getNorthEast().lng / 2]);
// FIXME Fit bounds first image overlay.
//map.fitBounds(bounds);
fetchGeometries(resourceId, mainImages[0].id);

// Geometries are displayed and edited on the drawnItems layer.
var drawnItems = new L.FeatureGroup();

var drawControl = new L.Control.Draw({
    draw: {
        polyline: true,
        polygon: true,
        rectangle: true,
        circle: true,
        marker: true,
        circlemarker: false,
    },
    edit: {
        featureGroup: drawnItems,
        remove: true,
    }
});
// Don't display the button "clear all".
L.EditToolbar.Delete.include({
    removeAllLayers: false,
});
map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));
map.addControl(drawControl);
map.addLayer(drawnItems);

/* Style Editor (https://github.com/dwilhelm89/Leaflet.StyleEditor) */
// Initialize the StyleEditor
var styleEditor = L.control.styleEditor({
    // position: 'topleft',
    // colorRamp: ['#1abc9c', '#2ecc71', '#3498db'],
    // markers: ['circle-stroked', 'circle', 'square-stroked', 'square'],
    strings: {
        // TODO Only cancel is updated.
        cancel: Omeka.jsTranslate('Finish'),
        cancelTitle: Omeka.jsTranslate('Cancel Styling'),
        tooltip: Omeka.jsTranslate('Click on the element you want to style'),
        tooltipNext: Omeka.jsTranslate('Choose another element you want to style'),
    },
    useGrouping: false,
});
map.addControl(styleEditor);

setView();

/* Manage geometries. */

// Handle adding new geometries.
map.on(L.Draw.Event.CREATED, function (element) {
    addGeometry(element.layer);
});

// // Handle editing geometries (when the edit button "save" is clicked).
// map.on(L.Draw.Event.EDITED, function(element) {
//     // TODO Check if options changed to avoid to save default ones.
//     // FIXME It doesn't work when a marker is moved or style edited.
//     element.layers.eachLayer(function(layer) {
//         editGeometry(layer);
//     });
// });
handleDrawEditSave();
// Do the save work after the edit stop, keeping previous style.
function handleDrawEditSave() {
    var editedLayers = {};

    catchEditEvents();

    // Catch the events.
    function catchEditEvents() {
        map.on(L.Draw.Event.EDITED, function(element) {
            editedLayers = element.layers;
        });
        map.on(L.Draw.Event.EDITSTOP, function(data) {
            saveLayerAtDrawStop();
        });
    }

    // Save it when edit stop.
    function saveLayerAtDrawStop() {
        if (editedLayers && editedLayers instanceof  L.LayerGroup) {
            editedLayers.eachLayer(function(layer) {
                editGeometry(layer);
            });
        }
    }
}

// Handle deleting geometries (when the delete button "save" is clicked).
map.on(L.Draw.Event.DELETED, function(element) {
    // TODO Don't delete geometry if issue on server.
    element.layers.eachLayer(function(layer) {
        deleteGeometry(layer);
    });
});

// Handle styling of a geometry.
map.on('styleeditor:changed', function(element){
    editGeometry(element);
});

// Handle paste wkt/geojson.
map.on('paste:layer-created', function(element){
    addGeometry(element.layer);
});

map.on('paste:layer-created', function(element) {
    map.addLayer(element.layer);
});

// Handle the image change.
map.on('baselayerchange', function(element){
    // TODO Keep the layers in a invisible feature group layer by image? Check memory size.
    drawnItems.clearLayers();
    fetchGeometries(resourceId, currentMediaId());
});

/* Various methods. */

// Manage the relations side bar.
// TODO Move specific code from StyleEditor here and use events.

// Store the current annotation identifier for easier processing after resource selection.
map.on('styleeditor:editing', function(element){
    // The annotation identifier is enough, but currently, the full layer/marker/geometry is saved.
    currentAnnotation = element;
});
map.on('styleeditor:hidden', function(element){
    currentAnnotation = null;
});

// Disable the core resource-form.js bind for the sidebar selector.
$(document).off('o:prepare-value');

/**
 * Add a new linked resource from the sidebar for the style editor.
 *
 * @see application/asset/js/resource-form.js
 * type: "resource"; value: empty; valueObj: data of one selected item; nameprefix: empty.
 */
$(document).on('o:prepare-value', function(e, type, value, valueObj, namePrefix) {
    if (!valueObj || typeof valueObj['value_resource_id'] === 'undefined') {
        return;
    }
    // Check if the current section is open.
    if ($('.section.active').prop('id') !== section || $('#' + section + ' .leaflet-styleeditor.editor-enabled').length !== 1) {
        return;
    }
    var identifier = currentAnnotation.annotationIdentifier
        || currentAnnotation.options.annotationIdentifier
        || null;
    if (!identifier) {
        alert(Omeka.jsTranslate('Unable to find the geometry.'));
        return;
    }

    // Check if the selected resource is already linked.
    var partIdentifier = currentMediaId();
    var url = basePath + '/admin/cartography/' + resourceId + '/geometries'
        + '?mediaId=' + (partIdentifier ? partIdentifier : '-1') + '&annotationId=' + identifier;

    $.get(url)
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }

            if (typeof data.geometries[identifier] !== 'undefined') {
                var oaLinking = data.geometries[identifier].options.oaLinking || [];
                var arrayLength = oaLinking.length;
                for (var i = 0; i < arrayLength; i++) {
                    if (oaLinking[i]['value_resource_id'] === valueObj['value_resource_id']) {
                        alert(Omeka.jsTranslate('The resource is already linked to the current annotation.'));
                        return;
                    }
                }
            }

            var resourceDataTypes = [
                'resource',
                'resource:item',
                'resource:itemset',
                'resource:media',
            ];
            if (!valueObj || resourceDataTypes.indexOf(type) === -1) {
                return;
            }

            addLinkedResource(identifier, valueObj);
            appendLinkedResource(valueObj);
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the geometries.');
            alert(message);
        });
});
var addLinkedResource = function(identifier, valueObj) {
    if (typeof currentAnnotation.options.oaLinking === 'undefined') {
        currentAnnotation.options.oaLinking = [];
    }
    currentAnnotation.options.oaLinking.push(valueObj);
    editGeometry(currentAnnotation);
};
var appendLinkedResource = function(valueObj) {
    // Prepare the markup for the resource data types.
    var html = '<div class="value selecting-resource">'
        + '<p class="selected-resource">'
        // TODO Add ellipsis to display the title and to display the resource icon.
        // + '<span class="o-title ' + valueObj['value_resource_name'] + '">';
        + '<span class="o-title ' + valueObj['value_resource_name'] + '-no">'
        + (typeof valueObj['thumbnail_url'] !== 'undefined' ? '<img src="' + valueObj['thumbnail_url'] + '">' : '')
        + '<a href="' + valueObj['url'] + '">'
        + (typeof valueObj['display_title'] === 'undefined' ? Omeka.jsTranslate('[Untitled]') : valueObj['display_title'])
        + '</a>'
        + '</span>'
        + '</p>'
        + '<ul class="actions">'
        + '<li><a class="o-icon-delete remove-value" title="' + Omeka.jsTranslate('Remove value') + '" href="#" aria-label="' + Omeka.jsTranslate('Remove value') + '" data-value-resource-id="' + valueObj['value_resource_id'] + '"></a></li>'
        + '</ul>'
        + '</div>';
    var oaLinkingDiv = $('.leaflet-styleeditor-oalinking.value.selecting-resource:visible');
    oaLinkingDiv.append(html);
};

/**
 * Remove a linked resource (directly via jQuery).
 */
$('#' + section).on('click', '.leaflet-styleeditor-interior .actions .remove-value', function (element) {
    if (!currentAnnotation || !currentAnnotation.options.oaLinking || currentAnnotation.options.oaLinking.length === 0) {
        return;
    }

    // Remove the linked resource from the list of linked resources.
    var oaLinking = currentAnnotation.options.oaLinking || [];
    var valueResourceId = $(this).data('value-resource-id');
    var exists = false;
    for (var i = 0; i < oaLinking.length; i++) {
        if (oaLinking[i]['value_resource_id'] == valueResourceId) {
            oaLinking.splice(i, 1);
            exists = true;
            break;
        }
    }
    if (!exists) {
        return;
    }

    currentAnnotation.options.oaLinking = oaLinking;
    editGeometry(currentAnnotation);

    // Remove the element from the style editor.
    $(this).closest('.value.selecting-resource').remove();
});

// Switching sections changes map dimensions, so make the necessary adjustments.
$('#' + section).one('o:section-opened', function(e) {
    map.invalidateSize();
    setView();
});

//Close the sidebar when switching sections to avoid possible issues between describe/locate.
$('#' + section).on('o:section-closed', function(e) {
    var sidebar = $('#select-resource');
    Omeka.closeSidebar(sidebar);
});

/**
 * Recursively remove the fonctions of an object.
 *
 * This is a hack to fix the edition of markers via leaflet.draw.
 * @todo Remove this hack used to allow markers to be edited.
 */
function buildParams(obj, key) {
    key = key || '';
    obj = obj || {};
    for (var prop in obj) {
        var element = obj[prop];
        if (typeof element === 'array') {
            element.map(function (ele, idx) {
                buildParams(ele, idx);
            });
        } else if (typeof element === 'object') {
            // Recursive looping.
            buildParams(element, prop);
        } else if (typeof element === 'function') {
            // Remove the fonction.
            obj[prop] = '';
        }
    }
}

});
