const baseUrl = window.location.pathname.replace(/\/admin\/.*/, '/');

/**
 * @description To extend the style-editor, put annotation dynamic data form inside the side bar.
 * This part is for rendering different types of elements like select, input, textarea, links, input-select…
 * To make a new type of element available, add a new service like createInputFieldService().
 *
 * @author Tom, cancms@163.com
 */
// Field services
(function (jQuery, L) {

    function wrapObj2Array(obj) {
        var arr = [];
        if (! $.isArray(obj)) {
            if (typeof(obj) === 'object' && (!$.isEmptyObject(obj))) {
                arr = [obj]
            } else {
                arr = [];
            }
        } else {
            arr = obj;
        }
        return arr;
    }

    function createElementDescriptiveHtmlService() {
        var renderService = {
            renderPlainHtml: renderPlainHtml,
        };
        var _renderData = {
            leafletLayer: {},
            propertyTplData: {},
            tplDataService: {}

        };
        var $ = jQuery;

        function setElementRenderData(propertyTplData, leafletLayer, tplDataService) {
            _renderData.propertyTplData = propertyTplData;
            _renderData.leafletLayer = leafletLayer;
            _renderData.tplDataService = tplDataService;
        }

        // the index of the metadata value
        function getValueAtIndex( metaKey = null, index = 'auto', propertyTplData = null) {
            propertyTplData = propertyTplData || _renderData.propertyTplData;

            var value = _renderData.tplDataService.getValueAtIndex(
                _renderData.leafletLayer,
                metaKey,
                index,
                propertyTplData
            );

            return value;
        }

        function renderPlainHtml(propertyTplData, leafletLayer, tplDataService) {
            setElementRenderData(propertyTplData, leafletLayer, tplDataService);

            var htmlRs = '';
            switch ( propertyTplData.type ) {
                case 'text':
                case 'textarea':
                case 'select':
                    htmlRs = inputFieldRenderHtml();
                    break;
                case 'resource':
                    htmlRs = resourceLinkRenderHtml();
                    break;
                case 'valuesuggest':
                    htmlRs = valueSuggestRenderHtml();
                    break;
                default:
                    break;
            }
            return htmlRs;
        }

        function isEmptyValue(value) {
            var rs = true;
            // if (value === '' || value === undefined || value === false) {
            if ( value ) {
                rs = false;
            } else if (value === 0) {
                rs = false;
            }
            return rs;
        }

        function inputFieldRenderHtml() {

            var value = getValueAtIndex();
            var label = _renderData.propertyTplData['o:label'];

            var html = '';
            if (!isEmptyValue(value)) {
                html = `<div class="_annotation_property_plain_html">
                    <span>${label}: </span> ${value}
                </div>`;
            }
            return html;
        }

        function resourceLinkRenderHtml() {

            var label = _renderData.propertyTplData['o:label'];
            var listLinks = getValueAtIndex(null, null) || [];
            listLinks = wrapObj2Array(listLinks);
            var html = '';
            if (!isEmptyValue(listLinks) && $.isArray(listLinks) && listLinks.length > 0) {
                listLinks.map(function (valueObj) {
                    var thumbnail = '';
                    var title = '';

                    if (valueObj['display_title'] ) {
                        title = valueObj['display_title'];
                    } else {
                        title = Omeka.jsTranslate('[Untitled]');
                    }

                    if (valueObj['thumbnail_url']) {
                        thumbnail = `<img src="${valueObj['thumbnail_url']}" title="${title}" alt="${title}">`;
                    }

                    html += `<div class=" value">
                                <p class="resource-oa-linking">
                                    <span class="o-title ${valueObj['value_resource_name']}-no" >
                                        ${thumbnail}
                                        <a href="${valueObj['url']}" title="${title}"
                                            style="
                                                display: inline-block;
                                                width: 180px;
                                                white-space: nowrap;
                                                overflow: hidden !important;
                                                text-overflow: ellipsis;
                                        ">${title}</a>
                                    </span>
                                </p>
                            </div>
                            `;
                });

                html = `<div class="_annotation_property_plain_html annotation-body-oa-linking">
                       <span>${label}: </span>  ${html}
                    </div>`;
            }

            return html;
        }

        function valueSuggestRenderHtml() {

            var label = _renderData.propertyTplData['o:label'];
            var value = getValueAtIndex() || {};

            var html = '';
            if (!isEmptyValue(value.value)) {
                html = `<div class="_annotation_property_plain_html">
                    <span>${label}: </span> ${value.value}
                </div>`;
            }

            return html;
        }

        return renderService;
    }

    function createElementRenderService(styleFormOptions = {}) {
        var renderService = {
            render: render,
        };

        var $ = jQuery;
        var _renderData = {
            propDataModel: {},
            propertyTplData: {},
            tplDataService: {},
            styleFormOptions: styleFormOptions
        };

        initialize();

        function initialize() {
        }

        function createJqElement(strHtml) {
            return $('<div/>').html(strHtml).contents();
        }
        function wrapper(element) {

            var wrapper = null;

            if ((typeof  element) === 'object') {
                wrapper = createJqElement(`<div class="annotation-attribute leaflet-styleeditor-uiElement">
                    <label class="leaflet-styleeditor-label"></label>
                </div> `)
                    .append(element);

            } else if ((typeof  element) === 'string') {
                wrapper = createJqElement(`<div class="annotation-attribute leaflet-styleeditor-uiElement">
                    <label class="leaflet-styleeditor-label"></label>
                    ${element}
                </div> `);
            }

            return wrapper;
        }
        function initElementLabel(wrapper, propertyData) {
            var propertyNode = wrapper.find('._annotate_property');
            propertyData = propertyData || {};

            // fill label
            var labelNode = wrapper.find('.leaflet-styleeditor-label');
            if (labelNode) {
                labelNode.html(propertyData['o:label'] + ': ');
            }
        }
        function getType(propertyTplData = null) {
            propertyTplData = propertyTplData || _renderData.propertyTplData;
            var rs = null;
            var type = propertyTplData['type'];
            var allowTypes = ['text', 'textarea', 'select', 'resource', 'valuesuggest'];
            if (type && ($.inArray(type, allowTypes) > -1)) {
                rs = type;
            }
            return rs;
        }
        function eventName(event) {
            return getEditorOption('styleEditorEventPrefix') + event;
        }

        function getEditorOption(key) {
            var rs = null;
            try {
                rs = _renderData.styleFormOptions.styleEditorOptions[key];
            } catch (e) {
                rs = null;
            }
            return rs;
        }

        function setElementRenderData(propertyTplData, propDataModel, tplDataService) {
            _renderData.propertyTplData = propertyTplData;
            _renderData.propDataModel = propDataModel;
            _renderData.tplDataService = tplDataService;
        }

        function render(propertyTplData, propDataModel, tplDataService) {
            setElementRenderData(propertyTplData, propDataModel, tplDataService);
            var element = null;
            switch (getType()) {
                case 'text':
                case 'textarea':
                case 'select':
                    element = createInputFieldService().createFieldElement();
                    break;
                case 'resource':
                    element = createResourceLinksService().createFieldElement();
                    break;
                case 'valuesuggest':
                    element = createValueSuggestService().createFieldElement();
                    break;
            }
            return element;
        }

        function appendDestroyEventsFn(element, eventNames = []) {
            element.annotateElementDestroyEvents = function () {
                eventNames.map(function (eName) {
                    getEditorOption('map').off(eventName(eName));
                });
            };
        }

        function createInputFieldService() {
            var service = {
                createFieldElement: createFieldElement,
            };
            var _data = {
                propertyTplData: _renderData.propertyTplData,
                propDataModel: _renderData.propDataModel
            };

            function fillPropertyData(element, propertyTemplateData) {
                propertyTemplateData = propertyTemplateData || {};
                var propertyField = element.find('._annotate_property');
                if (propertyField) {
                    var initValue = _data.propDataModel.getPropertyData(
                        propertyTemplateData['o:term'],
                        _renderData.propertyTplData._jsFnGetUniqueKey()
                    );

                    propertyField.val(initValue);
                }
            }

            function initElementSelectOptions(selectElement, propertyData) {
                var options = propertyData['value_options'] || {};
                $.each(options, function (key, value) {
                    var option = `<option value="${key}">${value}</option>`;
                    selectElement.append(option);
                });
            }

            // bind events
            function initElementEvents(propertyNode, propertyData) {
                propertyNode.change(function () {
                    _data.propDataModel.doOnPropertyChange({
                        newData: {
                            key: propertyData['o:term'],
                            value: propertyNode.val(),
                        },
                        jqDomElement: propertyNode,
                        propertyTplData: propertyData,
                    });
                });
            }

            function initElement(wrapper, propertyData) {
                if (!wrapper) {
                    return false;
                }
                var propertyNode = wrapper.find('._annotate_property');
                propertyData = propertyData || {};

                initElementLabel(wrapper, propertyData);

                // add the select options
                if (propertyNode && propertyData.type === 'select') {
                    initElementSelectOptions(propertyNode, propertyData);
                }

                // set the value data from geometry item
                fillPropertyData(wrapper, propertyData);

                // bind the change event, input, select
                if (propertyNode) {
                    initElementEvents(propertyNode, propertyData);
                }
            }

            function createFieldElement() {
                var element = '<input class="_annotate_property leaflet-styleeditor-input " />';
                switch (getType()) {
                    case 'textarea':
                        element = '<textarea class="_annotate_property leaflet-styleeditor-input"></textarea>';
                        break;
                    case 'select':
                        element = '<select class="_annotate_property leaflet-styleeditor-select "></select>';
                        break;
                }
                var wrapperElement = wrapper(element);
                initElement(wrapperElement, _data.propertyTplData);

                appendDestroyEventsFn(wrapperElement, []);
                return wrapperElement;
            }

            return service;
        }

        function createResourceLinksService () {
            var resourceLinksService = {
                createFieldElement: createFieldElement,
            };

            var listLinks = [];

            var _data = {
                linkItemsDiv: null,
                sourcePropertyKey: _renderData.propertyTplData['o:term'],
                events: {
                    onAddNewResourceItem: 'onAddNewResourceItem'
                },
                resourcePropertyTplData: {} // clone
            };

            initialize();

            function initialize() {

                // deep copy
                _data.resourcePropertyTplData = $.extend(true, {}, _renderData.propertyTplData);

                listLinks = _renderData.propDataModel.getPropertyData(
                    _data.sourcePropertyKey,
                    _data.resourcePropertyTplData._jsFnGetUniqueKey()
                    // null
                ) || [];

                listLinks = wrapObj2Array(listLinks);

                // While opening the right sidebar,
                // the editor is expecting a return of the new link item
                getEditorOption('map').on(eventName(_data.events.onAddNewResourceItem), function (data) {
                    doOnNewLinkItemReturn(data);
                });
            }

            function removeLinkItem(resourceId) {
                listLinks = listLinks.filter(function (item) {
                    return (item.value_resource_id !== resourceId);
                });
                _renderData.propDataModel.doOnPropertyChange({
                    newData: {key: _data.sourcePropertyKey, value: listLinks},
                    propertyTplData: _data.resourcePropertyTplData
                });
            }

            function addLinkItem(linkItem) {
                listLinks.push(linkItem);
                _renderData.propDataModel.doOnPropertyChange({
                    newData: {key: _data.sourcePropertyKey, value: listLinks},
                    propertyTplData: _data.resourcePropertyTplData
                });
            }

            function onDeleteClick(resourceId) {
                removeLinkItem(resourceId);
                reRenderLinkItems();
            }

            function doOnNewLinkItemReturn(data) {
                data = data || {};
                var linkItem = data.newChoseItem;
                if (linkItem) {
                    addLinkItem(linkItem);
                    reRenderLinkItems();
                }

                if (data.afterFinishAddItemCallback && $.isFunction (data.afterFinishAddItemCallback)) {
                    data.afterFinishAddItemCallback({
                        layer: _renderData.propDataModel.currentLayer()
                    });
                }
            }

            function createFieldElement() {

                var linkItemsDiv = `<div class="leaflet-styleeditor-oalinking value selecting-resource _oaLinking"
                                            ></div>`;

                // add the list div
                var wrapperElement = wrapper(linkItemsDiv);

                // the add button
                wrapperElement.append(addLinkButton());

                // name the label
                initElementLabel(wrapperElement, _renderData.propertyTplData);

                // mark the list div
                _data.linkItemsDiv = wrapperElement.find('._oaLinking');

                // list the items
                reRenderLinkItems();

                appendDestroyEventsFn(wrapperElement, [_data.events.onAddNewResourceItem]);
                return wrapperElement;
            }

            function reRenderLinkItems() {
                if (! _data.linkItemsDiv) {
                    return false;
                }
                // empty all the child nodes
                _data.linkItemsDiv.empty();

                var itemElements = createLinkItemElements( );
                _data.linkItemsDiv.append(itemElements);

            }

            function createLinkItemElements() {
                var itemTplFn = function (itemData) {
                    var itemTpl = `<div class="value selecting-resource">
                         <p class="selected-resource">
                             <span class="o-title items-no">
                                 <img class=""
                                       src="${itemData.thumbnail_url || ''}"
                                       alt="${itemData.display_title || ''}"
                                       title="${itemData.display_title || ''}"
                                       >
                                 <a class="" href="${itemData.url || ''}">${itemData.display_title || ''}</a>
                              </span>
                          </p>
                          <ul class="actions">
                              <li class="">
                                  <a class="o-icon-delete remove-value"
                                      href="#"
                                      data-value-resource-id="${itemData.value_resource_id || ''}"
                                      title="Remove value"
                                      aria-label="Remove value"></a>
                               </li>
                           </ul>
                      </div>`;
                    var element = createJqElement(itemTpl);
                    // bind action
                    element.find('.actions .remove-value').click(function () {
                        onDeleteClick(itemData.value_resource_id);
                    });
                    return element;
                };
                var itemElements = $();
                listLinks.map(function (item) {
                    itemElements = itemElements.add(itemTplFn(item));
                });
                return itemElements;
            }

            // For admin.
            function addLinkButton() {
                var sideBarContentUrl = baseUrl + 'admin/item/sidebar-select';
                var addBtn = `<a class="leaflet-styleeditor-linking o-icon-items button resource-selection"
                                 href="#item-resource-select"
                                 id="item-resource-select-button"
                                 data-sidebar-content-url="${sideBarContentUrl}">
                             Add links</a>`;

                var btnElement = createJqElement(addBtn);
                btnElement.click(function () {
                    _openOmekaSidebar ( );
                });

                return btnElement;
            }

            function _openOmekaSidebar ( ) {
                // There may be multiple style editors in tabs, so use the map of the current tab.
                let selectButton = $('.section.active .leaflet-styleeditor.editor-enabled .button.resource-selection');
                let sidebar = $('#select-resource');
                let term = 'oa:hasBody';
                $('#select-item a').data('property-term', term);
                // Mark the oaLinking div as selecting-resource
                // so the core resource-form.js triggers
                // o:prepare-value after selection.
                $('.selecting-resource').removeClass('selecting-resource');
                var oaDiv = $('.section.active .leaflet-styleeditor-oalinking.value');
                oaDiv.addClass('selecting-resource');
                Omeka.populateSidebarContent(sidebar, selectButton.data('sidebar-content-url'));
                Omeka.openSidebar(sidebar);
            }

            return resourceLinksService;
        }

        function createValueSuggestService () {
            var elementService = {
                createFieldElement: createFieldElement,
            };

            var _data = {
                // suggestionLabelKey: 'label',
                suggestionLabelKey: 'value',
            };

            function fillPropertyData(element) {
                var propertyField = element.find('._annotate_property');
                if (propertyField) {
                    var initValue = _renderData.propDataModel.getPropertyData(
                        _renderData.propertyTplData['o:term'],
                        _renderData.propertyTplData._jsFnGetUniqueKey()
                    );

                    initValue = initValue || {};
                    propertyField.val(initValue[_data.suggestionLabelKey] || '');
                }

            }

            // bind events
            function doOnValueChange(propertyNode, suggestion) {
                suggestion = suggestion || {};
                suggestion.data = suggestion.data || {};

                var newValue = {};
                newValue[_data.suggestionLabelKey] = suggestion.value || '';
                newValue['uri'] = suggestion.data.uri || '';

                _renderData.propDataModel.doOnPropertyChange({
                    newData: {
                        key: _renderData.propertyTplData['o:term'],
                        value: newValue,
                    },
                    jqDomElement: propertyNode,
                    propertyTplData: _renderData.propertyTplData,
                });
            }

            function initElement(wrapper, propertyData) {
                if (!wrapper) {
                    return false;
                }
                var propertyNode = wrapper.find('._annotate_property');
                propertyData = propertyData || {};

                initElementLabel(wrapper, propertyData);

                // set the value data from geometry item
                fillPropertyData(wrapper);

                // bind the change event, input, select
                if (propertyNode) {
                    // styleInputElement(propertyNode);
                    configAutoComplete(propertyNode);
                }
            }

            function configAutoComplete(propertyNode) {
                if (! propertyNode) {
                    return false;
                }
                propertyNode.autocomplete({
                    serviceUrl: _renderData.propertyTplData.valuesuggest.service_url,
                    params: {query: propertyNode.val()},
                    deferRequestBy: 200,
                    minChars: 3,
                    // Must disable preventBadQueries or autocomplete will not fire on
                    // queries that share a root that previously returned no results.
                    preventBadQueries: false,
                    // Must disable triggerSelectOnValidInput or onSelect will be
                    // triggered whether the user wants it or not. The user must
                    // explicitly select the suggestion.
                    triggerSelectOnValidInput: false,
                    onSearchStart: function() {
                        // $(this).css('cursor', 'progress');
                    },
                    onSearchComplete: function(query, suggestions) {
                        // $(this).css('cursor', 'default');
                    },
                    onSearchError: function (query, jqXHR, textStatus, errorThrown) {
                        // Silently handle error.
                        // $(this).css('cursor', 'default');
                    },
                    // Prepare the value when the user selects a suggestion.
                    onSelect: function (suggestion) {
                        // Set value as URI type
                        propertyNode.val(suggestion.value);
                        doOnValueChange(propertyNode, suggestion);
                    },
                    // Prepare the suggestions prior to rendering them.
                    beforeRender: function(container, suggestions) {
                        // Add title attribute to each suggestion for disambiguation.
                        container.children().each(function(index) {
                            $(this).attr('title', suggestions[index].data.info);
                        });
                    }
                });
            }

            function createFieldElement() {
                var element = '<input class="_annotate_property leaflet-styleeditor-input " />';
                var wrapperElement = wrapper(element);
                initElement(wrapperElement, _renderData.propertyTplData);
                appendDestroyEventsFn(wrapperElement, []);
                return wrapperElement;
            }

            return elementService;

        }

        return renderService;
    }

    L.StyleEditorAnnotation = L.StyleEditorAnnotation || {};
    L.StyleEditorAnnotation.createElementRenderService = createElementRenderService;
    L.StyleEditorAnnotation.createElementDescriptiveHtmlService = createElementDescriptiveHtmlService;

}(jQuery, L));

/**
 * @Description: To extend the style-editor, put annotation dynamic data form
 *               inside the side bar
 * @Author: Tom, cancms@163.com
 */
// Form services
(function (jQuery, L) {

    function createAnnotateFormService() {
        var formService = {
            renderView: renderView,
            renderDescriptiveHtml: renderDescriptiveHtml,
            isEmptyValue: isEmptyValue,
        };

        function createPropertyTemplateDataService(styleFormOptions = {}) {
            var service = {
                // getLayerMetaData: getLayerMetaData,
                getValueAtIndex: getValueAtIndex,
                setJsonData: setJsonData,
                getJsonData: getJsonData,
                getTypeData: getTypeData,
                getTypeResourceProperties: getTypeResourceProperties,
                getFirstTypeId: getFirstTypeId,
                hasTypes: hasTypes,
                hasTypeId: hasTypeId,
                getTypeSelectInitValue: getTypeSelectInitValue
            };
            var $ = jQuery;
            var _data = {
                jsonData: [],
                sourceTypeDefaultId: -1,
                allTypeIds: [],
            };

            initialize();

            function initialize() {
                _data.jsonData = styleFormOptions.styleEditorOptions.annotationFormData;
                initTemplateData();
            }

            function getJsonData() {
                return _data.jsonData;
            }

            // re initialize the data
            function setJsonData(templateJson) {
                styleFormOptions.styleEditorOptions.annotationFormData = templateJson;
                _data.jsonData = templateJson;
                initTemplateData();
            }

            function hasTypes() {
                return (_data.jsonData.length > 0);
            }

            function getTypeData(typeId) {
                var rs = _data.jsonData.find(function (item) {
                    return item['o:id'] === typeId;
                }) || {};
                return rs;
            }

            function getTypeResourceProperties(typeId) {
                var rs = getTypeData(typeId)['o:resource_template_property'] || [];
                return rs;
            }

            function getFirstTypeId() {
                var id = _data.sourceTypeDefaultId;
                try {
                    id = _data.jsonData[0]['o:id'];
                } catch (e) {
                }
                return id;
            }

            function initTemplateData() {
                _data.jsonData = _data.jsonData || [];
                _data.jsonData.map(function (item) {
                    if (!item) {
                        return false;
                    }

                    _initTemplateTypeData(item);
                    _indexSameProperties(item['o:resource_template_property']);
                    // item['o:resource_template_property'] = item['o:resource_template_property'] || [];
                    // _initTemplatePropertiesData( item['o:resource_template_property']);

                });
            }

            function _indexSameProperties(propertiesData) {
                var isMultipleTypeOfProperty = function (propertyItem) {
                    propertyItem = propertyItem || {};
                    var rs = true;
                    if (propertyItem.type === 'resource') {
                        rs = false;
                    }
                    return rs;
                };

                var setPropertyIndex = function (propertyItem, index) {
                    if (!propertyItem) {
                        return false;
                    }
                    // add the _jsUniqueKey
                    propertyItem._jsFnGetUniqueKey = function () {
                        var rs = index;
                        if (! isMultipleTypeOfProperty(propertyItem)) {
                            rs = null;
                        }
                        return rs;
                    };
                    propertyItem._jsFnIsArrayProperty = function () {
                        var rs = true;
                        if (! isMultipleTypeOfProperty(propertyItem)) {
                            rs = false;
                        }
                        return rs;
                    };
                };
                propertiesData = propertiesData || [];
                groupBy(propertiesData, function (item2) {
                    return item2['o:term'];
                }).map(function (arrGroupedItems) {
                    arrGroupedItems.map(function (propertyItem, index) {
                        setPropertyIndex(propertyItem, index);
                    });
                });
            }

            function _initTemplateTypeData(typeData) {
                typeData = typeData || {};
                typeData['o:id'] = typeData['o:id'] || _data.sourceTypeDefaultId;
                typeData['o:resource_template_property'] = typeData['o:resource_template_property'] || [];
                typeData['o:label'] = typeData['o:label'] || (typeData.placeholder || 'Select type below...');
                _data.allTypeIds.push(typeData['o:id']);
            }

            function _initTemplatePropertiesData(propertiesData) {
                propertiesData.map(function (propertyItem, idx) {
                    if (!propertyItem) {
                        return false;
                    }
                    // add the _jsUniqueKey
                    propertyItem._jsFnGetUniqueKey = function (onlyIndex = true) {
                        return idx;
                    };
                    propertyItem._jsFnIsArrayProperty = function () {
                        return true;
                    };
                });
            }

            function hasTypeId(typeId) {
                return ($.inArray(typeId, _data.allTypeIds) > -1);
            }

            function getTypeSelectInitValue(useTypeId) {
                var rs = _data.sourceTypeDefaultId;
                if (hasTypeId(useTypeId)) {
                    rs = useTypeId;
                } else {
                    rs = getFirstTypeId();
                }
                return rs;
            }

            function getLayerMetaData(leafletLayer, metaKey, dataIndex = 0) {
                leafletLayer = leafletLayer || {};
                var rs = '';
                try {
                    rs = leafletLayer.options.metadata[metaKey];
                    if (dataIndex !== null && rs && $.isArray(rs)) {
                        rs = rs[dataIndex];
                    }
                }catch (e) {
                    rs = '';
                }
                return rs;
            }

            // the index of the metadata value
            function getValueIndex(propertyTplData) {
                propertyTplData = propertyTplData || {};
                var valueIndex = 0;
                if (propertyTplData._jsFnIsArrayProperty && propertyTplData._jsFnIsArrayProperty()
                    && propertyTplData._jsFnGetUniqueKey
                    && $.isFunction(propertyTplData._jsFnGetUniqueKey)
                ) {
                    valueIndex = propertyTplData._jsFnGetUniqueKey() || 0;
                }
                return valueIndex;
            }

            function getValueAtIndex(leafletLayer, metaKey = null, index = 'auto', propertyTplData = null) {
                propertyTplData = propertyTplData || {};

                var valueIndex = 0;
                if (index === 'auto') {
                    valueIndex = getValueIndex(propertyTplData);
                } else {
                    valueIndex = null;
                }
                metaKey = metaKey || propertyTplData['o:term'];

                var value = getLayerMetaData(
                    leafletLayer,
                    metaKey,
                    valueIndex
                );

                return value;
            }

            return service;
        }

        function createPropertiesDataModel(styleFormOptions = {}) {
            var service = {
                getSourceTypeId: getSourceTypeId,
                setSourceTypeId: setSourceTypeId,
                doOnPropertyChange: doOnPropertyChange,
                getPropertyData: getPropertyData,
                currentLayer: currentLayer,
            };
            var $ = jQuery;
            var _data = {
                sourceTypeDefaultId: -1,
                styleFormOptions: {},
                currentLayer: null,
                unifiedLayerOptionsData: {},
                // formTypeIdString: 'resourceTypeId'
                formTypeIdString: 'o:resource_template' // ex: options['resource_template] = 5
            };

            initialize();

            function initialize() {
                _data.styleFormOptions = styleFormOptions;

                getEditorOption('map').on(eventName('editing'), function (layer) {
                    _data.currentLayer = currentLayer();
                    _data.unifiedLayerOptionsData = getUnifiedLayerOptionsData();
                });
            }

            function getEditorOption(key) {
                var rs = null;
                try {
                    rs = _data.styleFormOptions.styleEditorOptions[key];
                } catch (e) {
                    rs = null;
                }
                return rs;
            }

            function eventName(event) {
                return getEditorOption('styleEditorEventPrefix') + event;
            }

            function currentLayer() {
                var layer = null;
                try {
                    layer = styleFormOptions.styleEditorOptions.util.getCurrentElement();
                } catch (e) {
                    layer = null;
                }

                return layer;
            }

            function unifyKeyTerm(key) {
                // use the rdf key from template

                // if (key) {
                //     // remove the ':', and convert to lower case,
                //     key = key.split(':').join('').toLowerCase();
                // }

                return key;
            }

            function getUnifiedLayerOptionsData() {
                var data = {};
                var layer = currentLayer();
                var options = {};
                if (layer) {
                    options = layer.options;
                }

                // ONLY operates on the options.metadata
                if (options && (!$.isEmptyObject(options))) {
                    var metaData = options.metadata || {};
                    $.each(metaData, function (key, value) {
                        if (key) {
                            data[unifyKeyTerm(key)] = value;
                        }
                    })
                }

                return data;

            }

            // the Leaflet Layer object
            function layerOptionChange(propertyKey, newValue, propertyTplData = null) {
                var layer = currentLayer();
                if (layer) {
                    if (propertyKey !== null) {
                        // ONLY operates on the options.metadata
                        layer.options.metadata = layer.options.metadata || {};
                        if (propertyTplData && propertyTplData._jsFnIsArrayProperty && propertyTplData._jsFnIsArrayProperty()) {
                            var uniqueKey = propertyTplData._jsFnGetUniqueKey() || 0;
                            layer.options.metadata[propertyKey] = layer.options.metadata[propertyKey] || [];
                            layer.options.metadata[propertyKey][uniqueKey] = newValue;
                        } else {
                            layer.options.metadata[propertyKey] = newValue;
                        }
                    }
                    // fire event for changed layer
                    getEditorOption('util').fireChangeEvent(layer)
                }
            }

            function getPropertyData(propertyName, index = null) {
                var rs = _data.unifiedLayerOptionsData[unifyKeyTerm(propertyName)];
                // the property data is array stored
                // options.metadata.first_name = ['a', 'b', 'c']
                if (index !== null && rs) {
                    rs = rs[index] || '';
                }
                return rs;
            }

            function setPropertyData(key, value, propertyTplData = null, layerOptionsMetaDataChange = true) {
                if (key !== null) {
                    _data.unifiedLayerOptionsData[unifyKeyTerm(key)] = value;
                    if (layerOptionsMetaDataChange === true) {
                        layerOptionChange(key, value, propertyTplData);
                    }
                }
                return service;
            }

            function getSourceTypeId() {
                var propertyKey = _data.formTypeIdString;
                return (getPropertyData(propertyKey) || _data.sourceTypeDefaultId);
            }

            function setSourceTypeId(id) {
                var propertyKey = _data.formTypeIdString;
                setPropertyData(propertyKey, id);

                return service;
            }

            function doOnPropertyChange(data) {
                // set
                var propertyKey = data.newData.key;
                var newValue = data.newData.value;
                var propertyTplData = data.propertyTplData;
                setPropertyData(propertyKey, newValue, propertyTplData);

            }

            return service;
        }

        function renderView(styleFormOptions = {}) {
            var service = {
                // createAnnotateForm: createAnnotateForm
            };
            var $ = jQuery;
            var _data = {
                styleFormOptions: {},

                tplDataService: {},  // the json template data
                propDataModel: {},  // the data from geoItem
                elementRenderService: {},  // the element render service

                placeHolderDiv: null, // the holder for the dynamic form

                // the dynamic form wrapper div element, a jq object
                createdFormDiv: null,

                // the property element, jq object
                createdPropertyElements: [],

            };

            initialize();

            function initialize() {
                _data.styleFormOptions = styleFormOptions;

                //  init data model service
                _data.propDataModel = createPropertiesDataModel(styleFormOptions);
                //  init the tpl element service
                _data.tplDataService = createPropertyTemplateDataService(styleFormOptions);
                _data.elementRenderService = L.StyleEditorAnnotation.createElementRenderService(styleFormOptions);

                // where to put the form
                addPlaceHolderDiv();

                // once the user clicks on an item, create the form
                getEditorOption('map').on(eventName('editing'), function (layer) {
                    createAnnotateForm();
                });

                getEditorOption('map').on(eventName('afterTemplateJsonReLoaded'), function (data) {
                    if (!(data && data.templateJsonData)) {
                        return false;
                    }
                    _data.tplDataService.setJsonData(data.templateJsonData);
                    createAnnotateForm();
                });
            }

            function eventName(event) {
                return getEditorOption('styleEditorEventPrefix') + event;
            }

            function getEditorOption(key) {
                var rs = null;
                try {
                    rs = _data.styleFormOptions.styleEditorOptions[key];
                } catch (e) {
                    rs = null;
                }
                return rs;
            }

            function addPlaceHolderDiv() {
                var div = _createJqElement('<div></div>');
                _data.styleFormOptions.styleEditorInterior.appendChild(_jqToDomElement(div));
                _data.placeHolderDiv = div;
            }

            function formDivTemplate() {
                var html = `<div>
                <div class="annotation-types leaflet-styleeditor-uiElement">
                    <label class="leaflet-styleeditor-label">Type:</label>
                    <select class="leaflet-styleeditor-select "></select>
                </div>

            <hr>
            </div>`;
                return html;
            }

            function createAnnotateForm() {
                if (_data.createdFormDiv) {
                    _data.createdFormDiv.remove();
                    _data.createdFormDiv = null;
                }
                if (!_data.tplDataService.hasTypes()) {
                    return false;
                }
                _data.createdFormDiv = _createJqElement(formDivTemplate());
                populateTypeSelect();
                _data.placeHolderDiv.append(_data.createdFormDiv);
            }

            function populateTypeSelect() {
                var select = _data.createdFormDiv.find('.annotation-types select');
                if (!select) {
                    return false;
                }
                $.each(_data.tplDataService.getJsonData(), function (idx, typeData) {
                    var option = `<option value="${typeData['o:id']}">${typeData['o:label']}</option>`;
                    select.append(option);
                });

                // set type's value
                var layerOptionTypeId = _data.propDataModel.getSourceTypeId();
                var initTypeId = _data.tplDataService.getTypeSelectInitValue(layerOptionTypeId);
                select.val(initTypeId);
                // if (initTypeId > 0) {
                //     _data.propDataModel.setSourceTypeId(initTypeId);
                // }
                _data.propDataModel.setSourceTypeId(initTypeId);

                // on select change
                select.change(function () {
                    var typeId = parseInt($(this).val());
                    // if (typeId > 0) {
                    //     _data.propDataModel.setSourceTypeId(typeId);
                    // }
                    _data.propDataModel.setSourceTypeId(typeId);

                    createAnnotateProperties();
                });
                // default,
                createAnnotateProperties();

            }

            function removeCreatedProperties() {
                _data.createdPropertyElements.map(function (item) {
                    if (item) {
                        if (item.annotateElementDestroyEvents && $.isFunction(item.annotateElementDestroyEvents)) {
                            item.annotateElementDestroyEvents();
                        }
                        item.remove();
                    }
                });
                _data.createdPropertyElements = [];
            }

            function createAnnotateProperties() {
                removeCreatedProperties();
                var layerTypeId = _data.propDataModel.getSourceTypeId();
                var typeId = layerTypeId;
                if (!_data.tplDataService.hasTypeId(layerTypeId)) {
                    typeId = _data.tplDataService.getFirstTypeId();
                }
                // var typeId = _data.propDataModel.getSourceTypeId() || _data.tplDataService.getFirstTypeId();
                $.each(_data.tplDataService.getTypeResourceProperties(typeId), function (idx, propertyData) {
                    var jqPropertyHtml = _data.elementRenderService.render(
                        propertyData,
                        _data.propDataModel,
                        _data.tplDataService
                    );

                    if (jqPropertyHtml) {

                        _data.createdPropertyElements.push(jqPropertyHtml);
                        _data.createdFormDiv.append(jqPropertyHtml);
                    }
                });

            }

            return service;
        }

        function renderDescriptiveHtml(layer, templateJson) {
            templateJson = templateJson || [];
            if ( ! (layer && (templateJson.length > 0)) ) {
                return '';
            }

            var styleFormOptions = {
                styleEditorOptions: {
                    annotationFormData: templateJson
                }
            };

            var tplDataService = createPropertyTemplateDataService(styleFormOptions);
            var elementRenderService = L.StyleEditorAnnotation.createElementDescriptiveHtmlService();

            var typeId = tplDataService.getValueAtIndex(layer, 'o:resource_template', null);

            // 3. Display informations about the annotation.
            var renderOwnerAndTime = function() {
                var owner = tplDataService.getValueAtIndex(layer, 'o:owner', null);
                var createdAt = tplDataService.getValueAtIndex(layer, 'o:created', null) || '';
                var html = '';

                var annotationId = tplDataService.getValueAtIndex(layer, 'o:id', null);
                if (annotationId) {
                    var linkUrl = baseUrl + currentPath + '/annotation/' + annotationId;
                    html += `<div class="_annotation_property_plain_html annotation-metadata ">
                                <div class="annotation-caption">
                                    <a class="resource-link" href="${linkUrl}">
                                        <span class="resource-name">[#${annotationId}]</span>
                                    </a>
                                    <ul class="actions">
                                        <li>
                                            <span><a class="o-icon-external" href="${linkUrl}"
                                                    target="_blank"
                                                    title="${Omeka.jsTranslate('Show annotation')}"
                                                    aria-label="${Omeka.jsTranslate('Show annotation')}"
                                                   ></a></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>`;
                }

                if (owner && owner.name) {
                    html += `<div class="_annotation_property_plain_html">
                        <div class="annotation-owner">${owner.name || ''}</div>
                        <div class="annotation-created">${createdAt}</div>
                    </div>`;
                }
                return html;
            };

            var elementsHtml = [];
            $.each(tplDataService.getTypeResourceProperties(typeId), function (idx, propertyData) {
                var jqPropertyHtml = elementRenderService.renderPlainHtml(
                    propertyData,
                    layer,
                    tplDataService
                );
                if (jqPropertyHtml) {
                    elementsHtml.push(jqPropertyHtml);
                }
            });

            var ownerAndTimeHtml = renderOwnerAndTime();
            if (ownerAndTimeHtml) {
                elementsHtml.push(ownerAndTimeHtml);
            }

            return elementsHtml.join('\n');
        }

        function _createJqElement(strHtml) {
            return $('<div/>').html(strHtml).contents();
        }

        function _jqToDomElement(jqElement) {
            var rs = null;
            if (jqElement) {
                rs = jqElement.get(0);
            }
            return rs;
        }
        function isEmptyValue(value) {
            var rs = true;
            // if (value === '' || value === undefined || value === false) {
            if ( value ) {
                rs = false;
            } else if (value === 0) {
                rs = false;
            }
            return rs;
        }

        // var result = groupBy(list, function(item)
        // {
        //   return [item.lastname, item.age];
        // });
        function groupBy(array, fn) {
            var groups = {};
            array.forEach(function (o) {
                var group = JSON.stringify(fn(o));
                groups[group] = groups[group] || [];
                groups[group].push(o);
            });
            return Object.keys(groups).map(function (group) {
                return groups[group];
            })
        }

        return formService;
    }

    L.StyleEditorAnnotation = L.StyleEditorAnnotation || {};
    L.StyleEditorAnnotation.createAnnotateFormService = createAnnotateFormService;

}(jQuery, L));

/*
 * Cartography annotate
 */
$(document).ready( function() {

// Prevent page scrolling/dragging when interacting with a Leaflet map.
(function() {
    var mapActive = false;
    var initContainer = function(el) {
        if (el._scrollFixed) return;
        el._scrollFixed = true;
        L.DomEvent.disableScrollPropagation(el);
        L.DomEvent.disableClickPropagation(el);
        el.addEventListener('mousedown', function() {
            mapActive = true;
        });
        el.addEventListener('wheel', function(e) {
            e.preventDefault();
        }, {passive: false});
    };
    document.addEventListener('mouseup', function() {
        mapActive = false;
    });
    // Firefox: block scroll while dragging on map.
    var lastScrollY = window.scrollY;
    document.addEventListener('scroll', function() {
        if (mapActive) {
            window.scrollTo(window.scrollX, lastScrollY);
        } else {
            lastScrollY = window.scrollY;
        }
    });
    var observer = new MutationObserver(function() {
        document.querySelectorAll('.leaflet-container')
            .forEach(initContainer);
    });
    observer.observe(document.body, {
        childList: true, subtree: true
    });
    document.querySelectorAll('.leaflet-container')
        .forEach(initContainer);
})();

/**
 * Fetch images metadata of a resource.
 *
 * @todo Remove the sync request and use a callback.
 *
 * @param int resourceId
 * @param object data May contaiin the image type (type: "original"…).
 * @return array
 */
var fetchImages = function(resourceId, data) {
    var url = baseUrl + currentPath + '/cartography/' + resourceId + '/images';

    $.ajax({url: url, data: data, async: false})
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            images = data.images;
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the images.');
            alert(message);
        });
}

/**
 * Fetch default wms layers of a site.
 *
 * @todo Remove the sync request and use a callback.
 *
 * @param int resourceId
 * @param object data May contain the level of wms layers to fetch (upper or lower)
 * @return array
 */
var fetchWmsLayers = function(resourceId, data) {
    var url = baseUrl + currentPath + '/cartography/' + resourceId + '/wmsLayers';

    $.ajax({url: url, data: data, async: false})
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            wmsLayers = data.wmsLayers;
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the wms layers.');
            alert(message);
        });
}

/**
 * Fetch geometries for a resource.
 *
 * @todo Separate the fetch and the display.
 *
 * @param int resourceId
 * @param array data May contaiin the media id.
 * @param L.FeatureGroup drawnItems
 */
var fetchGeometries = function(resourceId, data, drawnItems) {
    var url = baseUrl + currentPath + '/cartography/' + resourceId + '/geometries';

    $.get(url, data)
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            if (data.geometries.length) {
                displayGeometries(data.geometries, drawnItems);
            }

            // Useful for data processing just after the drawnItems are added to
            // the map.
            if (drawnItems && drawnItems._map && drawnItems._map.fireEvent) {
                drawnItems._map.fireEvent('fetchGeometries:done', {
                    returnData: data,
                    drawnItems: drawnItems
                });
            }
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
 *
 * @param array geometries
 * @param L.FeatureGroup drawnItems
 */
var displayGeometries = function(geometries, drawnItems) {
    geometries.forEach(displayGeometry, {drawnItems: drawnItems});
}

/**
 * Display one geometry on a feature group.
 *
 * @param array geometries
 * @param L.FeatureGroup drawnItems (value inside callback reference)
 */
var displayGeometry = function(data) {
    var layer;
    var geojson = Terraformer.wktToGeoJSON(data['wkt']);
    var options = data['options'] || {};
    options.annotationIdentifier = data['id'];

    // Prepare to set the content of the popup in all cases, not only description.
    options.onEachFeature = function(feature, layer) {
        // var popupContent = popupAnnotation(options);
        // layer.bindPopup(popupContent);

        // To reserve the options from geoJson.
        layer.options = layer.options || {};
        // To prepare for style editor form-element initial value.
        layer.options = $.extend(options, layer.options);

        cartographyDataService.bindLayerPopup(layer);
    }

    // Keep the styling.
    options.style = function (feature) {
        return options;
    }

    // Prepare the layer.
    if (geojson.type === 'Point' && typeof options.radius !== 'undefined') {
        // Circle: coordinates are inversed on an image.
        layer = L.circle([geojson.coordinates[1], geojson.coordinates[0]], options);
        layer.setStyle(options);
        cartographyDataService.bindLayerPopup(layer);

    } else if (geojson.type === 'Point') {
        // Marker: restore custom icon if styled, else default.
        var latlng = [geojson.coordinates[1], geojson.coordinates[0]];
        // Icon name may be stored as iconName (new) or icon
        // (old). Ignore icon if it is an object (serialized
        // L.Icon from old data).
        var iconName = options.iconName
            || (typeof options.icon === 'string' ? options.icon : null);
        // Remove any serialized icon object — it is not a valid
        // L.Icon instance and would break L.marker().
        if (options.icon && typeof options.icon !== 'string') {
            delete options.icon;
        }
        if (options.iconColor && iconName && L.StyleEditor
            && L.StyleEditor.marker
            && L.StyleEditor.marker.GlyphiconMarker
        ) {
            var gm = new (L.StyleEditor.marker.GlyphiconMarker)();
            var markerIcon = gm.createMarkerIcon({
                iconSize: options.iconSize
                    || gm.options.size.small,
                iconColor: options.iconColor,
                icon: iconName,
            });
            layer = L.marker(latlng, $.extend({}, options, {icon: markerIcon}));
        } else {
            layer = L.marker(latlng, options);
        }
        layer.options = $.extend(options, layer.options);
        cartographyDataService.bindLayerPopup(layer);

    } else {
        layer = L.geoJson(geojson, options);

        // Use rectangle if possible, not Polygon.
        // Keep the moving handle when editing with leaflet.draw.
        if (options._isRectangle === '1') {
            layer = L.rectangle(layer.getBounds(), options);
            // Reserve the id of rectangle.
            if (options.annotationIdentifier) {
                rectangleIds[options.annotationIdentifier] = true;
            }
            cartographyDataService.bindLayerPopup(layer);
        }
    }

    // Set the content of the popup in all cases, not only description.
    // var popupContent = popupAnnotation(options);
    // layer.bindPopup(popupContent);

    // Append the geometry to the map.
    addGeometry(layer, data['id'], this.drawnItems);

    layer.options.annotationIdentifier = data['id'];
}

/**
 * Add a geometry to the map.
 *
 * @param layer
 * @param int identifier
 * @param drawnItems
 */
var addGeometry = function(layer, identifier, drawnItems) {
    // Don't save the geometry two times: if there is an identifier, it means
    // an existing geometry that was fetched.
    if (identifier) {
        layer.options.annotationIdentifier = identifier;
        addNonGroupLayers(layer, drawnItems);
        layer.options.annotationIdentifier = identifier;
        return;
    }

    var geojson = layer.toGeoJSON();
    var wkt;
    // Convert GeoJSON to WKT, handling Feature/FeatureCollection.
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.geojsonToWKT(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.geojsonToWKT(geojson.geometry);
    }

    var options = {};
    prepareSaveOptions(layer, options);

    var url = baseUrl + currentPath + '/cartography/annotate';
    var data = {
        // Identifier is always empty when an annotation is created.
        id : identifier,
        resource_id: resourceId,
        // Media id is empty on locate.
        media_id: currentMediaId(),
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
            layer.options.annotationIdentifier = identifier;
            // Reserve the rectangle layer id.
            if (identifier && layer instanceof L.Rectangle) {
                rectangleIds[identifier] = true;
            }

            if (permissionService) {
                permissionService.addUserIdForNewGeometryItem(layer);
            }

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
    var identifier = layer.options.annotationIdentifier || getMarkerIdentifier(layer);
    if (!identifier) {
        alert(Omeka.jsTranslate('Unable to save the edited geometry: no identifier.'));
        return;
    }

    var geojson = layer.toGeoJSON();
    var wkt;
    // Convert GeoJSON to WKT, handling Feature/FeatureCollection.
    if (geojson.features && geojson.features[0].geometry) {
        wkt = Terraformer.geojsonToWKT(geojson.features[0].geometry);
    } else {
        wkt = Terraformer.geojsonToWKT(geojson.geometry);
    }
    prepareSaveOptions(layer, layer.options);

    var url = baseUrl + currentPath + '/cartography/annotate';
    var data = {
        id: identifier,
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
    var url = baseUrl + currentPath + '/cartography/delete-annotation';
    var identifier = layer.options.annotationIdentifier || getMarkerIdentifier(layer);
    if (!identifier) {
        console.log('Unable to delete the geometry: no identifier.');
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
 * Create the popup content from the options of the geometry.
 *
 * @todo Use a template file to display the popup (common/cartography-popup.phtml).
 *
 * @param options
 */
var popupAnnotation = function(options) {
    var html = '';

    // Set default values if missing in original data.
    // TODO Check if to set default values is still needed (creation).
    options['metadata'] = options['metadata'] || '';
    options['metadata']['o:created'] = options['metadata']['o:created'] || '';
    options['metadata']['o:modified'] = options['metadata']['o:modified'] || '';
    options['metadata']['o:owner'] = options['metadata']['o:owner'] || {};
    options['metadata']['o:owner']['id'] = options['metadata']['o:owner']['id'] || '';
    options['metadata']['o:owner']['name'] = options['metadata']['o:owner']['name'] || '';

    var metadata = options['metadata'];
    var annotationIdentifier = options.annotationIdentifier || null;
    var url = '';

    // 0. Display popup content? This is the popup content!
    var content = options.popupContent || '';
    if (content.length) {
        html += '<div class="annotation-">' + content + '</div>';
    }

    // 1. Display body text (rdf:value) and motivation.
    var rdfValue = metadata['rdf:value'] || [];
    if (rdfValue.length) {
        html += '<div class="annotation-body-rdf-value">';
        rdfValue.forEach(function(val) {
            html += '<div>' + (typeof val === 'string' ? val : (val['@value'] || val['display_title'] || '')) + '</div>';
        });
        html += '</div>';
    }
    var motivation = metadata['oa:motivatedBy'] || [];
    if (motivation.length) {
        html += '<div class="annotation-motivation"><i>'
            + Omeka.jsTranslate('Motivation:') + '</i> ';
        motivation.forEach(function(val, i) {
            if (i > 0) html += ', ';
            html += (typeof val === 'string' ? val : (val['@value'] || ''));
        });
        html += '</div>';
    }
    var purpose = metadata['oa:hasPurpose'] || [];
    if (purpose.length) {
        html += '<div class="annotation-purpose"><i>'
            + Omeka.jsTranslate('Purpose:') + '</i> ';
        purpose.forEach(function(val, i) {
            if (i > 0) html += ', ';
            html += (typeof val === 'string' ? val : (val['@value'] || ''));
        });
        html += '</div>';
    }

    // 2. Display resource links (oa:hasBody).
    var oaLinking = metadata['oa:hasBody'] || [];
    if (oaLinking.length) {
        html += '<div class="annotation-body-oa-linking" >';
        // html += '<label>' + (oaLinking.length === 1 ? Omeka.jsTranslate('Related item') : Omeka.jsTranslate('Related items')) + '</label>';
        oaLinking.forEach(function(valueObj, index) {
            html += '<div class="value">'
                + '<p class="resource-oa-linking">'
                // TODO Add ellipsis to display the title and to display the resource icon.
                // + '<span class="o-title ' + valueObj['value_resource_name'] + '">';
                + '<span class="o-title ' + valueObj['value_resource_name'] + '-no">'
                + (typeof valueObj['thumbnail_url'] !== 'undefined' ? '<img src="' + valueObj['thumbnail_url'] + '">' : '')
                + '<a href="' + valueObj['url'] + '">'
                + (typeof valueObj['display_title'] === 'undefined' ? Omeka.jsTranslate('[Untitled]') : valueObj['display_title'])
                + '</a>'
                + '</span>'
                + '</p>'
                + '</div>';
        });
        html += '</div>';
    }

    // 3. Display informations about the annotation.
    html += '<div class="annotation-metadata">';
    if (annotationIdentifier) {
        url = baseUrl + currentPath + '/annotation/' + annotationIdentifier;
        html += '<div class="annotation-caption">'
            + '<a class="resource-link" href="' + url + '">'
            + '<span class="resource-name">[#' + annotationIdentifier + ']</span>'
            + '</a>'
            + '<ul class="actions"><li><span>'
            + '<a class="o-icon-external" href="' + url + '" target="_blank" title="' + Omeka.jsTranslate('Show annotation') + '" aria-label="' + Omeka.jsTranslate('Show annotation') + '"></a>'
            + '</span></li></ul>'
            + '</div>';
    }
    html += '<div class="annotation-owner">' + metadata['o:owner']['name'] + '</div>';
    html += '<div class="annotation-created">' + metadata['o:created'] + '</div>';
    html += '</div>';

    return html;
}

/**
 * Adjust the saving options data before sending to server (circle, rectangle).
 *
 */
var prepareSaveOptions = function(layer, options) {
    layer.options = layer.options || {};

    if (typeof layer.getRadius === 'function') {
        layer.options.radius = options.radius = layer.getRadius();
    }

    // Keep the rectangle information of the Polygon, so to have a moving center
    // point when edit with leaflet.draw.
    var id = layer.options.annotationIdentifier || 'not_existed';
    // Check if it is rectangle/square or a polygon.
    if (layer instanceof L.Rectangle
        || layer.options._isRectangle === '1'
        || rectangleIds[id] === true
    ) {
        options._isRectangle = '1';
    }

    // Remove bulky icon data from saved options: only keep
    // iconColor, icon, iconSize — the icon is rebuilt on load.
    delete options.iconUrl;
    delete options.shadowUrl;
    if (options.icon && typeof options.icon === 'object') {
        // L.Icon/L.DivIcon object — extract just the parameters.
        var iconOpts = options.icon.options || options.icon;
        options.iconColor = iconOpts.iconColor || options.iconColor;
        options.iconName = iconOpts.icon || options.iconName;
        options.iconSize = iconOpts.iconSize || options.iconSize;
        delete options.icon;
    }
}

/**
 * Recursively remove the fonctions of an object.
 *
 * This is a hack to fix the edition of markers via leaflet.draw.
 *
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

/**
 * Add fetched geometries to the drawnItems.
 *
 * Required since leaflet.draw doesn't support recursive feature group this way.
 *
 * @see https://github.com/Leaflet/Leaflet/issues/4461
 * @todo Fix: the existing groups are not draggable (but the newly created were).
 *
 * @todo Remove these specific layers, and use drawnItems only.
 * Note: there is a layer for "describe" and another one for "locate".
 */
var addNonGroupLayers = function(sourceLayer, targetGroup) {
    if (!targetGroup) {
        return;
    }
    if (sourceLayer instanceof L.LayerGroup) {
        sourceLayer.eachLayer(function (layer) {
            addNonGroupLayers(layer, targetGroup);
        });
    } else {
        targetGroup.addLayer(sourceLayer);
    }
}

/**
 * Get marker identifier.
 *
 * @todo Fix this process, too hacky: the identifier should be simple to save and find.
 *
 * @param layer
 * @return int
 */
var getMarkerIdentifier = function(layer) {
    var identifier = layer.options.annotationIdentifier;
    if (identifier) {
        return identifier;
    }
    var parents = Object.values(layer._eventParents);
    return parents[parents.length - 1].options.annotationIdentifier;
}

/**
 * Get the media id of the current image overlay.
 *
 * There is no media id in "locate", since anything is georeferenced and related
 * to the item.
 *
 * @todo Finish the cleaning in order to use only the service.
 *
 * @return int|null
 */
var currentMediaId = function() {
    // Quick hack to get the current map (but in public, the two maps are not hidden and there is no fragment).
    var section = window.location.hash.substr(1);
    // Fix crappy urls (universal viewer).
    section = section.indexOf('?') == -1 ? section : section.substr(0, section.indexOf('?'));
    if (!section.length) {
        if (currentMapElement !== 'annotate-describe') {
            return null;
        }
    } else if (section !== 'describe') {
        return null;
    }

    var mediaId = imageMediaService.getMediaId();

    return mediaId;
}

/**
 * Fit bounds from the current image layer (describe) or from the geometries (locate).
 *
 * @todo Fit map bounds according to geometries for "Locate", and according to size for "Describe".
 */
var setView = function() {
    // TODO Fit map bounds according to geometries.
};

/**
 * Add specific controls to annotate.
 *
 * @todo Remove argument drawnItems
 *
 * @var L.Map map
 * @var L.FeatureGroup drawnItems
 */
var annotateControl = function(map, drawnItems) {

    var drawControlOptions = {
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
    };

    drawControlOptions = permissionService.adjustDrawOptions(drawControlOptions);

    var drawControl = new L.Control.Draw(drawControlOptions);
    // Don't display the button "clear all".
    L.EditToolbar.Delete.include({
        removeAllLayers: false,
    });
    map.addControl(drawControl);

    /* Style Editor (https://github.com/dwilhelm89/Leaflet.StyleEditor) */

    // Marker icon base path (Leaflet default marker PNG).
    var _markerIconBase = baseUrl + 'modules/Cartography/asset/vendor/leaflet/images/';

    if (L.StyleEditor && L.StyleEditor.marker) {
        // Replace Mapbox URLs with local Leaflet marker PNG.
        var _markerPngUrl = function() {
            return _markerIconBase + 'marker-icon-2x.png';
        };
        if (L.StyleEditor.marker.DefaultMarker) {
            L.StyleEditor.marker.DefaultMarker.prototype
                ._getMarkerUrl = _markerPngUrl;
        }

        // GlyphiconMarker: use Leaflet marker PNG with FA icon.
        if (L.StyleEditor.marker.GlyphiconMarker) {
            L.StyleEditor.marker.GlyphiconMarker.prototype
                ._getMarkerUrl = _markerPngUrl;
            L.StyleEditor.marker.GlyphiconMarker.prototype
                .getMarkerHtml = function(size, color, icon) {
                var inner = icon
                    ? '<i class="fas ' + icon + '"></i>'
                    : '';
                return '<div class="cartography-marker'
                    + ' cartography-marker-'
                    + this.sizeToName(size)[0] + '">'
                    + inner
                    + '</div>';
            };
            L.StyleEditor.marker.GlyphiconMarker.prototype
                .createMarkerIcon = function(opts) {
                var size = opts.iconSize;
                var sizeName = this.sizeToName(size)[0];
                // Dimensions and anchors matching Leaflet
                // default marker exactly (no shift on click).
                var cfg = {
                    s: {size: [25, 41], anchor: [12, 41], popup: [1, -34]},
                    m: {size: [30, 50], anchor: [15, 50], popup: [0, -42]},
                    l: {size: [35, 58], anchor: [17, 58], popup: [1, -50]},
                };
                var c = cfg[sizeName] || cfg.s;
                return L.divIcon({
                    className: 'leaflet-styleeditor-glyphicon-marker-wrapper',
                    html: this.getMarkerHtml(size, opts.iconColor, opts.icon),
                    iconSize: c.size,
                    iconAnchor: c.anchor,
                    popupAnchor: c.popup,
                    icon: opts.icon,
                    iconColor: opts.iconColor,
                });
            };
            L.StyleEditor.marker.GlyphiconMarker.prototype
                .options.size = {
                small: [25, 41],
                medium: [30, 50],
                large: [35, 58],
            };
            L.StyleEditor.marker.GlyphiconMarker.prototype
                .options.markers = [
                '', 'fa-map-marker-alt', 'fa-thumbtack', 'fa-star',
                'fa-heart', 'fa-home', 'fa-flag', 'fa-bookmark',
                'fa-tag', 'fa-circle', 'fa-square', 'fa-university',
                'fa-landmark', 'fa-monument', 'fa-church', 'fa-tree',
                'fa-globe-americas', 'fa-map-pin', 'fa-crosshairs',
                'fa-camera', 'fa-eye', 'fa-search', 'fa-user',
                'fa-envelope', 'fa-music', 'fa-pencil-alt',
                'fa-lock', 'fa-cog', 'fa-road', 'fa-parking',
                'fa-hotel', 'fa-hospital', 'fa-school', 'fa-store',
                'fa-industry', 'fa-warehouse', 'fa-dot-circle',
                'fa-plus', 'fa-minus', 'fa-times', 'fa-check',
                'fa-cloud', 'fa-film', 'fa-print', 'fa-inbox',
                'fa-trash-alt',
            ];
        }
    }

    // Fix icon selector clicks: the original _createColorSelect
    // uses childNodes traversal to find the click target, which
    // fails with the nested HTML. Use closest() instead.
    if (L.StyleEditor && L.StyleEditor.formElements
        && L.StyleEditor.formElements.IconElement
    ) {
        var _IconEl = L.StyleEditor.formElements.IconElement;
        _IconEl.prototype._createColorSelect = function(color) {
            if (!this.options.selectOptions) {
                this.options.selectOptions = {};
            }
            if (color in this.options.selectOptions) return;
            var uiEl = this.options.uiElement;
            var ul = L.DomUtil.create(
                'ul', this._selectOptionWrapperClasses, uiEl
            );
            var markers = this.options.styleEditorOptions.util
                .getMarkersForColor(color);
            var self = this;
            markers.forEach(function(icon) {
                var li = L.DomUtil.create(
                    'li', self._selectOptionClasses, ul
                );
                var img = self._createSelectInputImage(li);
                self._styleSelectInputImage(img, icon, color);
            });
            this.options.selectOptions[color] = ul;
            L.DomEvent.addListener(ul, 'click', function(evt) {
                evt.stopPropagation();
                if (evt.target.nodeName === 'UL') return;
                var el = evt.target.closest(
                    '.leaflet-styleeditor-select-image'
                );
                if (el) {
                    self._selectMarker({target: el});
                }
            });
        };
        // Allow empty icon (no FA, just the Leaflet pin).
        _IconEl.prototype._styleSelectInputImage = function(t, e, i) {
            if (e === null || e === undefined) {
                e = t.getAttribute('value');
                if (e === null || e === undefined) return;
            }
            var o = this.options.styleEditorOptions.markerType
                .getIconOptions();
            if (i) o.iconColor = i;
            t.innerHTML = '';
            this.options.styleEditorOptions.markerType
                .createSelectHTML(t, o, e);
            t.setAttribute('value', e);
        };
        _IconEl.prototype._selectMarker = function(t) {
            var e = t.target.getAttribute('value');
            if (e === null || e === undefined) return;
            this.options.selectBoxImage.setAttribute('value', e);
            this.setStyle(e);
            this._hideSelectOptions();
        };
    }

    // Initialize the StyleEditor.
    var styleEditorControlOptions = {
        strings: {
            save: Omeka.jsTranslate('Save'),
                saveTitle: Omeka.jsTranslate('Save Styling'),
                cancel: Omeka.jsTranslate('Cancel'),
                cancelTitle: Omeka.jsTranslate('Cancel Styling'),
                tooltip: Omeka.jsTranslate('Click on the element you want to style'),
                tooltipNext: Omeka.jsTranslate('Choose another element you want to style'),
        },
        useGrouping: false,
        defaultMarkerColor: '#2A81CB',
        defaultMarkerIcon: '',
        colorRamp: [
            '#2A81CB', '#1abc9c', '#2ecc71', '#3498db',
            '#9b59b6', '#34495e', '#16a085', '#27ae60',
            '#2980b9', '#8e44ad', '#2c3e50', '#f1c40f',
            '#e67e22', '#e74c3c', '#95a5a6', '#f39c12',
            '#d35400', '#c0392b', '#bdc3c7', '#7f8c8d',
        ],
        markerType: L.StyleEditor
            && L.StyleEditor.marker
            && L.StyleEditor.marker.GlyphiconMarker
            ? L.StyleEditor.marker.GlyphiconMarker
            : undefined,
    };

    if (userRights && userRights.edit !== false) {

        // cartographyDataService.setMap(map);
        // use the annotation dynamic form for style-editor
        styleEditorControlOptions = $.extend(styleEditorControlOptions, {
            useAnnotationDynamicForm: true,
            createAnnotateFormServiceFn: L.StyleEditorAnnotation.createAnnotateFormService,
            annotationFormData: cartographyDataService.getAnnotationFormTemplateData() || [],
        });

        var styleEditor = L.control.styleEditor(styleEditorControlOptions);
        map.addControl(styleEditor);
    }

    // The permission control.
    permissionService
        .setMap(map)
        .applyControl();
}

/**
 * Initialize the data for the describe section.
 */
var initDescribe = function() {
    var section = 'describe';

    // TODO Convert the fetch of images into a callback.
    fetchImages(resourceId, {type: 'original'});
    if (!images.length) {
        $('#annotate-' + section).html(Omeka.jsTranslate('There is no image attached to this resource.'));
        return;
    }

    var pasteControl = userRights.create;

    // Initialize the map without a default view — fitBounds
    // will set the correct zoom after adding the image overlay.
    var map = L.map('annotate-' + section, {
        minZoom: -4,
        maxZoom: 8,
        maxBoundsViscosity: 1,
        crs: L.CRS.Simple,
        pasteControl: pasteControl
    });
    var mapMoved = false;

    cartographyDataService.setMap(map, section);

    // Geometries are displayed and edited on the drawnItems layer.
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    var baseMaps = {};
    images.forEach(function(image, index) {
        // Compute image edges as positive coordinates.
        // TODO Choose top left as 0.0 for still images?
        var southWest = L.latLng(0, 0);
        var northEast = L.latLng(image.size[1], image.size[0]);
        var bounds = L.latLngBounds(southWest, northEast);
        var imageOverlay = L.imageOverlay(image.url, bounds, {imageData: image});
        if (index === 0) {
            imageOverlay.addTo(map);
            imageMediaService.setMediaId(image.id);
            imageMediaService.setImageView(imageOverlay, map);
            // Store for later re-fitBounds on tab switch.
            describeMap = map;
            describeBounds = bounds;
            fetchGeometries(resourceId, {mediaId: image.id}, drawnItems);
        }
        baseMaps[Omeka.jsTranslate('Image #') + (index + 1)] = imageOverlay;
    });
    if (Object.keys(baseMaps).length > 1) {
        var layerControl = L.control.layers(baseMaps);
        map.addControl(new L.Control.Layers(baseMaps));
    }

    map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));

    if (userRights.create) {
        annotateControl(map, drawnItems);
    }

    // Tab may not have its final size at init time; recompute the container
    // size and refit bounds once layout is settled.
    setTimeout(function () {
        map.invalidateSize();
        if (describeBounds) {
            map.fitBounds(describeBounds);
        }
    }, 200);

    // Handle the image change (only for describe).
    currentMapElement = 'annotate-describe';
    map.on('baselayerchange', function(element){
        currentMapElement = 'annotate-describe';

        // Save current view before switching.
        var prevMediaId = imageMediaService.getMediaId();
        if (prevMediaId) {
            imageMediaService.saveView(map, prevMediaId);
        }

        drawnItems.clearLayers();

        // Set the new image id.
        try {
            var imageId = element.layer.options.imageData.id;
            imageMediaService.setMediaId(parseInt(imageId));
            imageMediaService.setImageView(element.layer, map);
        } catch (e) {
            imageMediaService.setMediaId(0);
        }

        fetchGeometries(resourceId, {mediaId: currentMediaId()}, drawnItems);
    });

    if (userRights.create) {
        annotateGeometries(map, section, drawnItems);
    }
}

/**
 * Initialize the data for the locate section.
 */
var initLocate = function() {
    var section = 'locate';

    // TODO Convert the fetch of wms layers into a callback.
    fetchWmsLayers(resourceId, {upper: 1, lower: 1});

    var pasteControl = userRights.create;

    // Initialize the map and set default view.
    var map = L.map('annotate-' + section, {
        pasteControl: pasteControl,
    });
    map.setView([20, 0], 2);
    var mapMoved = false;

    cartographyDataService.setMap(map, section);

    // TODO Create automatically the bounds from geometries.
    var defaultBounds = null;
    // defaultBounds = [southWest, northEast];

    // Add layers and controls to the map.
    if (typeof baseMaps === 'undefined') {
        let baseMaps = {};
    }
    if (typeof baseMaps !== 'object' || $.isEmptyObject(baseMaps)) {
        baseMaps = {
            'Streets': L.tileLayer.provider('OpenStreetMap.Mapnik'),
            /* 'Grayscale': L.tileLayer.provider('OpenStreetMap.BlackAndWhite'), // Removed from OpenStreetMap https://github.com/leaflet-extras/leaflet-providers/issues/316 */
            'Satellite': L.tileLayer.provider('Esri.WorldImagery'),
            'Terrain': L.tileLayer.provider('Esri.WorldShadedRelief'),
        };
    }

    if (!wmsLayers.length) {
        var layerControl = L.control.layers(baseMaps);
        map.addControl(new L.Control.Layers(baseMaps));
    } else {
        // Adapted from mapping-block.js (module Mapping).
        var noOverlayLayer = new L.GridLayer();
        var groupedOverlays = {
            'Overlays': {
                // 'No overlay': noOverlayLayer,
            },
        };

        // Set and prepare opacity control, if there is an overlay layer.
        var openWmsLayer, openWmsLabel;
        var opacityControl;
        var handleOpacityControl = function(overlay, label) {
            if (opacityControl) {
                // Only one control at a time.
                map.removeControl(opacityControl);
                opacityControl = null;
            }
            if (overlay !== noOverlayLayer) {
                // The "No overlay" overlay gets no control.
                opacityControl =  new L.Control.Opacity(overlay, label);
                map.addControl(opacityControl);
            }
        };

        // Add grouped WMS overlay layers.
        map.addLayer(noOverlayLayer);
        wmsLayers.forEach(function(data, index) {
            var wmsLabel = data.label.length ? data.label : (Omeka.jsTranslate('Layer') + ' ' + (index + 1));
            // Leaflet requires the layers and the styles separated.
            // Require a recent browser (@url https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams#Browser_compatibility#Browser_compatibility).
            // TODO Add a check and pure js to bypass missing URL interface.
            var url =  new URL(data.url);
            var searchParams = url.searchParams;
            var wmsLayers = '';
            wmsLayers = searchParams.get('LAYERS') || searchParams.get('Layers') || searchParams.get('layers') || wmsLayers;
            searchParams.delete('LAYERS'); searchParams.delete('Layers'); searchParams.delete('layers');
            var wmsStyles = '';
            wmsStyles = searchParams.get('STYLES') || searchParams.get('Styles') || searchParams.get('styles') || wmsStyles;
            searchParams.delete('STYLES'); searchParams.delete('Styles'); searchParams.delete('styles');
            url.search = searchParams;
            var wmsUrl = url.toString();
            if (wmsUrl.indexOf('?') === -1) {
                wmsUrl += '?';
            }
            wmsLayer = L.tileLayer.wms(wmsUrl, {
                layers: wmsLayers,
                styles: wmsStyles,
                format: 'image/png',
                transparent: true,
            });
            // Open the first wms overlay by default.
            if (index === 0) {
                openWmsLayer = wmsLayer;
                openWmsLabel = wmsLabel;
            }
            groupedOverlays['Overlays'][wmsLabel] = wmsLayer;
        });
        L.control.groupedLayers(baseMaps, groupedOverlays, {
            // exclusiveGroups: ['Overlays'],
        }).addTo(map);
    }
    map.addLayer(baseMaps['Satellite']);

    // Geometries are displayed and edited on the drawnItems layer.
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    // TODO Fix and add the fit bound control with geometries, not markers.
    fetchGeometries(resourceId, {mediaId: 0}, drawnItems);

    map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));

    var geoSearchControl = new window.GeoSearch.GeoSearchControl({
        provider: new window.GeoSearch.OpenStreetMapProvider,
        showMarker: false,
        retainZoomLevel: true,
    });
    map.addControl(geoSearchControl);
    map.addControl(new L.control.scale({'position': 'bottomleft', 'metric': true, 'imperial': false}));

    if (userRights.create) {
        annotateControl(map, drawnItems);
    }

    // Append the opacity control at the end of the toolbar for better ux.
    if (typeof openWmsLayer !== 'undefined' && openWmsLayer) {
        map.removeLayer(noOverlayLayer);
        map.addLayer(openWmsLayer);
        handleOpacityControl(openWmsLayer, openWmsLabel);

        // Handle the overlay opacity control.
        map.on('overlayadd', function(e) {
            handleOpacityControl(e.layer, e.name);
        });
    }

    setView();

    // TODO Remove this event.
    // Useless, only to get the media id in Describe.
    map.on('baselayerchange', function(element){
        currentMapElement = 'annotate-locate';
    });

    if (userRights.create) {
        annotateGeometries(map, section, drawnItems);
    }
}

/**
 * Initialize the data for the geo-browse section.
 */
var initGeobrowse = function() {
    var section = 'geobrowse';

    // TODO Convert the fetch of wms layers into a callback.
    // fetchWmsLayers(resourceId, {upper: 1, lower: 1});

    var pasteControl = userRights.create;

    // Initialize the map and set default view.
    var map = L.map('annotate-' + section, {
        pasteControl: pasteControl,
    });
    map.setView([20, 0], 2);
    var mapMoved = false;

    // TODO Create automatically the bounds from geometries.
    var defaultBounds = null;
    // defaultBounds = [southWest, northEast];

    // Add layers and controls to the map.
    if (typeof baseMaps === 'undefined') {
        let baseMaps = {};
    }
    if (typeof baseMaps !== 'object' || $.isEmptyObject(baseMaps)) {
        baseMaps = {
            'Streets': L.tileLayer.provider('OpenStreetMap.Mapnik'),
            /* 'Grayscale': L.tileLayer.provider('OpenStreetMap.BlackAndWhite'), // Removed from OpenStreetMap https://github.com/leaflet-extras/leaflet-providers/issues/316 */
            'Satellite': L.tileLayer.provider('Esri.WorldImagery'),
            'Terrain': L.tileLayer.provider('Esri.WorldShadedRelief'),
        };
    }

    if (!wmsLayers.length) {
        var layerControl = L.control.layers(baseMaps);
        map.addControl(new L.Control.Layers(baseMaps));
    } else {
        // Adapted from mapping-block.js (module Mapping).
        var noOverlayLayer = new L.GridLayer();
        var groupedOverlays = {
            'Overlays': {
                // 'No overlay': noOverlayLayer,
            },
        };

        // Set and prepare opacity control, if there is an overlay layer.
        var openWmsLayer, openWmsLabel;
        var opacityControl;
        var handleOpacityControl = function(overlay, label) {
            if (opacityControl) {
                // Only one control at a time.
                map.removeControl(opacityControl);
                opacityControl = null;
            }
            if (overlay !== noOverlayLayer) {
                // The "No overlay" overlay gets no control.
                opacityControl =  new L.Control.Opacity(overlay, label);
                map.addControl(opacityControl);
            }
        };

        // Add grouped WMS overlay layers.
        map.addLayer(noOverlayLayer);
        wmsLayers.forEach(function(data, index) {
            var wmsLabel = data.label.length ? data.label : (Omeka.jsTranslate('Layer') + ' ' + (index + 1));
            // Leaflet requires the layers and the styles separated.
            // Require a recent browser (@url https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams#Browser_compatibility#Browser_compatibility).
            // TODO Add a check and pure js to bypass missing URL interface.
            var url =  new URL(data.url);
            var searchParams = url.searchParams;
            var wmsLayers = '';
            wmsLayers = searchParams.get('LAYERS') || searchParams.get('Layers') || searchParams.get('layers') || wmsLayers;
            searchParams.delete('LAYERS'); searchParams.delete('Layers'); searchParams.delete('layers');
            var wmsStyles = '';
            wmsStyles = searchParams.get('STYLES') || searchParams.get('Styles') || searchParams.get('styles') || wmsStyles;
            searchParams.delete('STYLES'); searchParams.delete('Styles'); searchParams.delete('styles');
            url.search = searchParams;
            var wmsUrl = url.toString();
            if (wmsUrl.indexOf('?') === -1) {
                wmsUrl += '?';
            }
            wmsLayer = L.tileLayer.wms(wmsUrl, {
                layers: wmsLayers,
                styles: wmsStyles,
                format: 'image/png',
                transparent: true,
            });
            // Open the first wms overlay by default.
            if (index === 0) {
                openWmsLayer = wmsLayer;
                openWmsLabel = wmsLabel;
            }
            groupedOverlays['Overlays'][wmsLabel] = wmsLayer;
        });
        L.control.groupedLayers(baseMaps, groupedOverlays, {
            // exclusiveGroups: ['Overlays'],
        }).addTo(map);
    }
    map.addLayer(baseMaps['Satellite']);

    // Geometries are displayed and edited on the drawnItems layer.
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    // TODO Fix and add the fit bound control with geometries, not markers.
    // fetchGeometries(resourceId, {mediaId: 0}, drawnItems);

    map.addControl(new L.Control.Fullscreen( { pseudoFullscreen: true } ));

    var geoSearchControl = new window.GeoSearch.GeoSearchControl({
        provider: new window.GeoSearch.OpenStreetMapProvider,
        showMarker: false,
        retainZoomLevel: true,
    });
    map.addControl(geoSearchControl);
    map.addControl(new L.control.scale({'position': 'bottomleft', 'metric': true, 'imperial': false}));

    if (userRights.create) {
        annotateControl(map, drawnItems);
    }

    var drawControlOptions = {
        draw: {
            polyline: false,
            polygon: false,
            rectangle: true,
            circle: true,
            marker: false,
            circlemarker: false,
        },
        edit: {
            featureGroup: drawnItems,
            edit: false,
            remove: false,
        }
    };
    var drawControl = new L.Control.Draw(drawControlOptions);
    // Don't display the button "clear all".
    L.EditToolbar.Delete.include({
        removeAllLayers: false,
    });
    map.addControl(drawControl);

    // Append the opacity control at the end of the toolbar for better ux.
    if (typeof openWmsLayer !== 'undefined' && openWmsLayer) {
        map.removeLayer(noOverlayLayer);
        map.addLayer(openWmsLayer);
        handleOpacityControl(openWmsLayer, openWmsLabel);

        // Handle the overlay opacity control.
        map.on('overlayadd', function(e) {
            handleOpacityControl(e.layer, e.name);
        });
    }

    setView();

    // TODO Remove this event.
    // Useless, only to get the media id in Describe.
    map.on('baselayerchange', function(element){
        currentMapElement = 'annotate-locate';
    });

    if (userRights.create) {
        annotateGeometries(map, section, drawnItems);
    }
}

/* Manage geometries. */

/**
 * Add specific controls to annotate.
 *
 * @todo remove argument "section".
 *
 * @var Leaflet.Map map
 * @var string section
 * @var L.FeatureGroup drawnItems
 */
var annotateGeometries = function(map, section, drawnItems) {
    // Handle adding new geometries.
    map.on('draw:created', function (element) {
        addGeometry(element.layer, null, drawnItems);
    });

    // // Handle editing geometries (when the edit button "save" is clicked).
    // map.on('draw:edited', function(element) {
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
            map.on('draw:edited', function(element) {
                editedLayers = element.layers;
            });
            map.on('draw:editstop', function(data) {
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
    map.on('draw:deleted', function(element) {
        // TODO Don't delete geometry if issue on server.
        element.layers.eachLayer(function(layer) {
            deleteGeometry(layer);
        });
    });

    // Handle styling of a geometry.
    // Handle styling of a geometry in real time.
    // map.on('styleeditor:changed', function(element){
    //     editGeometry(element);
    // });
    // Use a final save button instead of saving in real time.
    handleStyleEditSave({
        popupAnnotation: popupAnnotation
    });
    function handleStyleEditSave(context) {
        var styleIsChanged = false;
        // The layer options data.
        var dataBeforeEditor = {};
        catchEditEvents();

        function styleEditorElementToMapLayer(element) {
            return map._layers[element._leaflet_id];
        }
        // Catch the events.
        function catchEditEvents() {
            map.on('styleeditor:changed', function(element) {
                if (element) {
                    styleIsChanged = true;
                    cartographyDataService.bindLayerPopup(styleEditorElementToMapLayer(element));
                }
            });

            map.on('styleeditor:editSave', function(element) {
                doSave(element);
            });

            // Align the popup content with the style editor.
            map.on('styleeditor:beforePopupContentChanging', function(data) {
                var element = data.currentElement || null;
                data.referenceData = data.referenceData || {};
                if (element && element.options && data.referenceData.inputText) {
                    element.options.popupContent = data.referenceData.inputText;
                    data.referenceData.inputText = context.popupAnnotation(element.options);
                }
            });

            // Before edit, keep the data for reset.
            map.on('styleeditor:visible', function() {
                var layers = map._layers || {};
                $.each(layers, function(id, layer) {
                    var options = layer.options || {};
                    dataBeforeEditor[id] = $.extend({}, options); // clone
                    dataBeforeEditor[id].metadata = $.extend({}, (options.metadata  || {})); // clone
                });
                // console.log(dataBeforeEditor);
            });

            map.on('styleeditor:editCancel', function(element) {
                doCancel(element);
            });
        }

        // Process the save.
        function doSave(element) {
            if (styleIsChanged === true && element ) {
                editGeometry(element);
                styleIsChanged = false;
            }
        }

        // Reserve the data for reset.
        function doCancel(element) {
            if (styleIsChanged === true && element) {
                styleIsChanged = false;

                var currentElementId = element.options.annotationIdentifier;

                var layers = map._layers || {};

                // Do the reset.
                $.each(layers, function(id, layer) {
                    if (layer.options.annotationIdentifier === currentElementId) {
                        layer.options = $.extend(layer.options, dataBeforeEditor[id] || {});

                        cartographyDataService.bindLayerPopup(layer);
                        if (layer.setStyle) {
                            layer.setStyle(layer.options);
                        }
                    }
                });
            }
        }
    }

    // Handle paste wkt/geojson.
    map.on('paste:layer-created', function(element){
        addGeometry(element.layer, null, drawnItems);
    });

    map.on('paste:layer-created', function(element) {
        map.addLayer(element.layer);
    });

    /* Various methods. */

    // Manage the relations side bar.
    // TODO Move specific code from StyleEditor here and use events.

    /**
     * Store the current annotation identifier for easier processing after resource selection.
     *
     * @todo To be removed: get the current annotation from leaflet (draw edit or style editor).
     */
    map.on('styleeditor:editing', function(element){
        // The annotation identifier is enough, but currently, the full layer/marker/geometry is saved.
        currentAnnotation = element;
    });
    /**
     * Reset the current annotation.
     *
     * @todo To be removed when the current annotation will be get from leaflet.
     */
    map.on('styleeditor:hidden', function(element){
        // TODO Revert change too when it is not in real time.
        currentAnnotation = null;
    });

    /**
     * Add a new linked resource from the sidebar for the style editor.
     *
     * @see application/asset/js/resource-form.js
     * type: "resource"; value: empty; valueObj: data of one selected item; nameprefix: empty.
     */
    // Resource linking handlers are registered once, outside
    // annotateControl(), to avoid duplication. See below.


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
        // Real time deletion deferred.
        // FIXME Real time delteion should be deferred (add event inside StyleEditor).
        editGeometry(currentAnnotation);

        // Remove the element from the style editor.
        $(this).closest('.value.selecting-resource').remove();
    });

    // Switching sections changes map dimensions, so make the necessary adjustments.
    $('#' + section).one('o:section-opened', function(e) {
        map.invalidateSize();
        setView();
    });

    // Close the sidebar when switching sections to avoid possible issues between describe/locate.
    $('#' + section).on('o:section-closed', function(e) {
        var sidebar = $('#select-resource');
        Omeka.closeSidebar(sidebar);
    });
}

/*
 * Initialization
 */

// TODO Remove global/closure variables.
// var resourceId;
// var cartographySections;
// TODO Check the right to annotate dynamically.
// var userId;
// var userRights;
var images = [];
var wmsLayers = [];

// Manage the distinction between the rectangles/squares and the polygons.
var rectangleIds = {};

// TODO Find the way to get the current annotation after the resource selection.
var currentAnnotation;

// Quick hack to keep the currentMapElement in public front-end.
// Only used to get the currentMediaId currently.
// TODO Find the way to get the current map after a base map selection (in public).
var currentMapElement = 'annotate-describe';

// The permission control.
var permissionService = createPermissionService(userId, userRights);

// Handle set, get the current image id
var imageMediaService = createImageMediaService();

// Disable the core resource-form.js bind for the sidebar selector.
$(document).off('o:prepare-value');

/**
 * Resource linking handlers — registered once globally.
 *
 * @see application/asset/js/resource-form.js
 */
// 1. Batch: checkboxes + "Add selected" button.
$(document).on('click', '.select-resources-button', function() {
    var checked = $('#item-results .resource').has('input.select-resource-checkbox:checked');
    if (!checked.length) return;
    checked.each(function() {
        var valueObj = $(this).data('resource-values');
        if (valueObj) {
            $(document).trigger('o:prepare-value', ['resource', null, valueObj]);
        }
    });
    Omeka.closeSidebar($('#select-resource'));
});
// 2. Single: click resource details → "Select resource".
$(document).on('click', '#select-item a', function() {
    var valueObj = $('.resource-details').data('resource-values');
    if (valueObj) {
        $(document).trigger('o:prepare-value', ['resource', null, valueObj]);
    }
    Omeka.closeSidebar($('#select-resource'));
});
// 3. Handle o:prepare-value to link the resource to the annotation.
$(document).on('o:prepare-value', function(e, type, value, valueObj, namePrefix) {
    if (!valueObj || typeof valueObj['value_resource_id'] === 'undefined') {
        return;
    }
    // Find the active section with an open style editor.
    var activeSection = $('.section.active').prop('id');
    if (!activeSection
        || $('#' + activeSection + ' .leaflet-styleeditor.editor-enabled').length !== 1
    ) {
        return;
    }
    if (!currentAnnotation || !currentAnnotation.options) {
        return;
    }
    var identifier = currentAnnotation.options.annotationIdentifier || null;
    if (!identifier) {
        alert(Omeka.jsTranslate('Unable to find the geometry.'));
        return;
    }

    var url = baseUrl + currentPath + '/cartography/' + resourceId + '/geometries';
    var partIdentifier = currentMediaId();
    var data = {
        media_id: partIdentifier === null ? '0' : (partIdentifier || '-1'),
        annotation_id: identifier,
    };

    $.get(url, data)
        .done(function(data) {
            if (data.status === 'error') {
                alert(data.message);
                return;
            }
            if (typeof data.geometries[identifier] !== 'undefined') {
                var oaLinking = data.geometries[identifier].options.oaLinking || [];
                for (var i = 0; i < oaLinking.length; i++) {
                    if (oaLinking[i]['value_resource_id'] === valueObj['value_resource_id']) {
                        alert(Omeka.jsTranslate('The resource is already linked to the current annotation.'));
                        return;
                    }
                }
            }
            var resourceDataTypes = ['resource', 'resource:item', 'resource:itemset', 'resource:media'];
            if (!valueObj || resourceDataTypes.indexOf(type) === -1) {
                return;
            }
            var map = cartographyDataService.getMap();
            if (map) {
                map.fireEvent('styleeditor:onAddNewResourceItem', {newChoseItem: valueObj});
            }
        })
        .fail(function(jqxhr) {
            var message = (jqxhr.responseText && jqxhr.responseText.substring(0, 1) !== '<')
                ? JSON.parse(jqxhr.responseText).message
                : Omeka.jsTranslate('Unable to fetch the geometries.');
            alert(message);
        });
});

// data service
var cartographyDataService = createCartographyDataService();
cartographyDataService.setPopupFn(popupAnnotation);

// Defer map initialization until the tab is visible, so
// Leaflet can compute the container size correctly.
var describeInitialized = false;
var locateInitialized = false;

// Store map references for later fitBounds.
var describeBounds = null;
var describeMap = null;
var locateMap = null;

var initDescribeWhenReady = function() {
    if (describeInitialized) {
        // Re-fit bounds when returning to the tab.
        if (describeMap && describeBounds) {
            describeMap.invalidateSize();
            describeMap.fitBounds(describeBounds);
        }
        return;
    }
    var el = document.getElementById('annotate-describe');
    if (!el || el.offsetWidth === 0) return;
    describeInitialized = true;
    cartographyDataService.setCurrentSection('describe');
    cartographyDataService.ajaxLoadDescribeLocateTemplateJson().then(function () {
        initDescribe();
    });
};

var initLocateWhenReady = function() {
    if (locateInitialized) {
        if (locateMap) {
            locateMap.invalidateSize();
        }
        return;
    }
    var el = document.getElementById('annotate-locate');
    if (!el || el.offsetWidth === 0) return;
    locateInitialized = true;
    cartographyDataService.setCurrentSection('locate');
    cartographyDataService.ajaxLoadDescribeLocateTemplateJson().then(function () {
        initLocate();
    });
};

$(document).on('click', 'a[href="#describe"]', function() {
    if (cartographySections.indexOf('describe') > -1) {
        setTimeout(initDescribeWhenReady, 100);
    }
});
$(document).on('click', 'a[href="#locate"]', function() {
    if (cartographySections.indexOf('locate') > -1) {
        setTimeout(initLocateWhenReady, 100);
    }
});

// Initialize immediately if the tab is already active (hash).
if (cartographySections.indexOf('describe') > -1) {
    setTimeout(initDescribeWhenReady, 200);
}
if (cartographySections.indexOf('locate') > -1) {
    setTimeout(initLocateWhenReady, 200);
}

if (cartographySections.indexOf('geobrowse') > -1) {
    // initLocate();
//    cartographyDataService.ajaxLoadDescribeLocateTemplateJson().then(function () {
        initGeobrowse();
//    });
}

});

// global data service, like annotation dynamic form template json

function createCartographyDataService() {
    var service = {
        getAnnotationFormTemplateData: getAnnotationFormTemplateData,
        ajaxLoadDescribeLocateTemplateJson: ajaxLoadDescribeLocateTemplateJson,
        bindLayerPopup: bindLayerPopup,
        setPopupFn: setPopupFn,
        setMap: setMap,
        getMap: getMap,
        setCurrentSection: setCurrentSection,
    };
    var _data = {
        annotationTemplateJson: {
            describe: [],
            locate: [],
        },
        currentSection: '',
        annotateFormService: L.StyleEditorAnnotation.createAnnotateFormService(),
        currentTemplateData: [],
        maps: {},
        popupFn: null
    };

    initialize();

    function initialize() {
        setCurrentSection();
        handleTabClick();
    }

    function setMap(map, section) {
        _data.maps[section] = map;
    }

    function getMap(section) {
        section = section || _data.currentSection;
        return _data.maps[section] || null;
    }

    function handleTabClick() {

        $.each({describe: 'describe-label', locate: 'locate-label'}, function (section, tabId) {
            var tabElement = $('#' + tabId);
            if (! tabElement) {
                return false;
            }
            tabElement.click(function () {
                setCurrentSection(section);
                setCurrentTemplateData(_data.annotationTemplateJson[section]);

                var map = _data.maps[section];
                if (map && map.fireEvent && $.isFunction(map.fireEvent)) {
                    map.fireEvent('styleeditor:afterTemplateJsonReLoaded', {
                        templateJsonData: _data.annotationTemplateJson[section]
                    });
                }

            });
        });
    }

    function validSection(section) {
        var rs = true;
        if (section !== 'describe' && section !== 'locate') {
            rs = false;
        }
        return rs;
    }

    function setCurrentSection (section = null) {
        if (section === null) {
            section =  window.location.hash.substr(1) ;
            section = section.indexOf('?') == -1
                ? section
                : section.substr(0, section.indexOf('?'));
            if (! validSection(section)) {
                section = 'describe';
            }
        }

        if (validSection(section)) {
            _data.currentSection = section;
        }

        return service;
    }
    function getCurrentSection (section = null) {
        return _data.currentSection;
    }

    function getAnnotationFormTemplateData() {
        return _data.currentTemplateData;
    }
    function getSectionTemplateData(section = null) {
        section = section || getCurrentSection();
        if (! validSection(section)) {
            return [];
        }
        return _data.annotationTemplateJson[section];
    }

    function setCurrentTemplateData(jsonData) {
        _data.currentTemplateData = jsonData;
    }

    function setAnnotationFormTemplateData(section, json) {
        if (validSection(section) && json) {
            _data.annotationTemplateJson[section] = json;
        }
        return service;
    }

    // For admin.
    function ajaxLoadDescribeLocateTemplateJson() {
        var requests = [];
        var dynamicPropertiesUrl = baseUrl + 'admin/cartography/resource-templates';
        ['describe', 'locate'].map(function (section) {

            // test
            // dynamicPropertiesUrl = `src/data/dy-form/${section}.json`;

            requests.push($.ajax({ url: dynamicPropertiesUrl, data: {type: section}}).done(function(data) {
                data = data || [];
                setAnnotationFormTemplateData(section, data);
            }));
        });
        // $.when.apply(undefined, requests).then(...)
        // var promise1 = $.when.apply({}, requests);
        // var rsPromise = $.when(promise1).then( function () {
        var rsPromise = $.when.apply({}, requests).then( function () {
            // set default current data
            setCurrentTemplateData(getSectionTemplateData());
        });
        return rsPromise;
    }

    function setPopupFn(fn) {
        _data.popupFn = fn;
    }

    function bindLayerPopup(layer) {
        if (!(layer instanceof L.Layer) ) {
            return false;
        }
        // Build popup from annotation metadata.
        var popContent = (layer.options && _data.popupFn)
            ? _data.popupFn(layer.options)
            : '';
        if (popContent) {
            // Set popup.
            if (layer.getPopup()) {
                layer.setPopupContent(popContent);
            } else {
                layer.bindPopup(popContent);
            }

        } else {
            // Remove popup.
            layer.unbindPopup();
        }

    }

    return service;

}

/**
 * The currentMediaId is the image id used to manage multiple background.
 */
function createImageMediaService() {
    var service = {
        setMap: setMap,
        setImageView: setImageView,
        setMediaId: setMediaId,
        getMediaId: getMediaId,
        saveView: saveView,
    };

    var _data = {
        currentMediaId: 0,
        viewedMediaIds: {},
        mediaDrawnItems: new L.FeatureGroup(),
        map: {}
    };

    function setMap(map) {
        _data.map = map;
        return service;
    }

    function setMediaId(id) {
        _data.currentMediaId = id;
        return service;
    }

    function getMediaId() {
        return _data.currentMediaId;
    }

    // ImageOverlay, the image layer.
    // Make the whole image inside the map in center
    function saveView(map, mediaId) {
        if (mediaId && _data.viewedMediaIds[mediaId]) {
            _data.viewedMediaIds[mediaId] = {
                center: map.getCenter(),
                zoom: map.getZoom()
            };
        }
    }

    function setImageView(imageOverlay, map) {
        var bounds = imageOverlay.getBounds();
        map.invalidateSize();
        var mediaId = _data.currentMediaId;
        if (!_data.viewedMediaIds[mediaId]) {
            // First view: fit bounds.
            _data.viewedMediaIds[mediaId] = true;
            map.fitBounds(bounds);
        } else if (typeof _data.viewedMediaIds[mediaId] === 'object') {
            // Subsequent views: restore saved state.
            map.setView(
                _data.viewedMediaIds[mediaId].center,
                _data.viewedMediaIds[mediaId].zoom
            );
        }
    }
    return service;
}

/**
 * @description Handle the permission by events fired from Style-Editor and
 * Leaflet-Draw, in which new events are added to extend their abilities to
 * handle permission for each layer.
 * @author Tom, cancms@163.com
 *
 * Handle user rights to create, edit, and delete items.
 *
 * There are four cases:
 * - public: display only;
 * - annotator: can create, but edit/delete are limited to own items;
 * - reviewer: all rights, except deletion is limited to own items;
 * - editor: all rights.
 *
 * @param userId
 * @param userRights Rights to create can be true/false, rights to edit and
 * delete can be true/false/"own".
 * @returns service
 */
function createPermissionService(userId, userRights = {create:false, edit:false, delete:false}) {
    var service = {
        applyControl: applyControl,
        setMap: setMap,
        adjustDrawOptions: adjustDrawOptions,
        addUserIdForNewGeometryItem: addUserIdForNewGeometryItem,
    };

    var controls = {};

    var _data = {
        map: {},
    };

    resetControls();
    permissionControl();

    function setMap(map) {
        _data.map = map;
        return service;
    }

    function applyControl( ) {
        _data.map.on('styleeditor:beforeInitChangeStyle', function (data) {
            if (!(data && data.control && data.layer)) {
                return false;
            }
            // If no edit permission, hide it.
            if (canEdit(data.layer) === false) {
                data.control.hideEditor();
                // Force exit inside the initChangeStyle() function in style-editor.
                data._dataFromOutside.forceExit = true;
            }
        });
        _data.map.on('leafletDraw:beforeEnableDelete', function (data) {
            if (!(data && data.control && data.layer)) {
                return false;
            }
            // If no  permission
            if (canDelete(data.layer) === false) {
                // Force exit inside the initChangeStyle() function in style-editor.
                data._dataFromOutside.forceExit = true;
            }
        });
        _data.map.on('leafletDraw:beforeEnableEdit', function (data) {
            if (!(data && data.control && data.layer)) {
                return false;
            }
            // If no  permission
            if (canEdit(data.layer) === false) {
                // Force exit inside the initChangeStyle() function in style-editor.
                data._dataFromOutside.forceExit = true;
            }
        });
    }

    function canEdit(layer) {
        var rs = false;
        if (controls.userRights.edit === true) {
            rs = true;
        } else if (controls.userRights.edit === 'own') {
            if (userId === getOwnerIdFromLayerOptions(layer)) {
                rs = true;
            }
        }
        return rs;
    }

    function canDelete(layer) {
        var rs = false;
        if (controls.userRights.delete === true) {
            rs = true;
        } else if (controls.userRights.delete === 'own') {
            if (userId === getOwnerIdFromLayerOptions(layer)) {
                rs = true;
            }
        }
        return rs;
    }

    // When new item is added, set the user id.
    function addUserIdForNewGeometryItem(layer) {
        layer.options = layer.options || {};
        layer.options.owner = layer.options.owner || {};
        layer.options.owner.id = userId;
    }

    function getOwnerIdFromLayerOptions(layer) {
        // Id = -1 means others's layer item.
        var id = -1;
        try {
            id = layer.options.owner.id;
        } catch (e) {
            id = -1;
        }
        return id;
    }

    function adjustDrawOptions(drawControlDefaultOptions) {

        var drawOptions = drawControlDefaultOptions.draw;

        if (controls.create === false) {
            drawOptions = false;
        }

        var drawControlOptions = {
            draw: drawOptions,
            edit: {
                featureGroup: drawControlDefaultOptions.edit.featureGroup,
                edit: (controls.userRights.edit !== false) ? true : false,
                remove: (controls.userRights.delete !== false) ? true : false,
            }
        };

        return drawControlOptions;
    }

    function permissionControl() {
        resetControls();

        if (userRights.create) {
            enableCreate();
        }
        if (userRights.edit) {
            enableEdit();
        }
        if (userRights.delete) {
            enableDelete();
        }
    }

    function resetControls() {
        controls = {
            create: false,
            edit: false,
            delete: false,
            userRights: userRights || {}
        };
    }

    function enableCreate() {
        controls.create = true;
    }

    function enableEdit() {
        controls.edit = true;
    }
    function enableDelete() {
        controls.delete = true;
    }

    return service;
}
