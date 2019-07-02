pimcore.registerNS("pimcore.object.tags.hrefTypeahead");
pimcore.object.tags.hrefTypeahead = Class.create(pimcore.object.tags.abstract, {

    type: "hrefTypeahead",
    dataChanged: false,
    /**
     * The init function, its called for each hrefTypeahead in a new object tab
     * @param data
     * @param fieldConfig
     */
    initialize: function (data, fieldConfig) {

        this.data = {};

        if (data) {
            this.data = data;
        }
        this.fieldConfig = fieldConfig;

    },

    /**
     * A simple copy paste from default href, it just makes sure data is displayed correctly in grid view
     * @param field
     * @return {{header: *, sortable: boolean, dataIndex: *, renderer: (*|(function(this:pimcore.object.tags.hrefTypeahead))), editor: *}}
     */
    getGridColumnConfig: function (field) {
        var renderer = function (key, value, metaData, record) {
            this.applyPermissionStyle(key, value, metaData, record);

            if (record.data.inheritedFields[key] && record.data.inheritedFields[key].inherited == true) {
                metaData.tdCls += " grid_value_inherited";
            }
            return value;

        }.bind(this, field.key);

        return {
            header: ts(field.label), sortable: false, dataIndex: field.key, renderer: renderer,
            editor: this.getGridColumnEditor(field)
        };
    },
    /**
     * Standard extjs store linking this.component(the combo box) to controller SearchController
     * @return {Ext.data.Store}
     */
    getHrefTypeaheadStore: function () {
        // We only support one class per field
        var classParam = this.fieldConfig.classes[0].classes;

        return Ext.create('Ext.data.Store', {
            autoDestroy: true,
            /**
             * After each action requestNicePathData will be called so even if the request returns empty, its ok because
             * the value that will be persisted its set by this.data.id not the element values itself
             */
            autoLoad: false,
            remoteSort: true,
            pageSize: 10,
            idProperty: 'fullpath',
            proxy: {
                type: 'ajax',
                url: '/admin/href-typeahead/find',
                reader: {
                    type: 'json',
                    rootProperty: 'data',
                },
                extraParams: {
                    fieldName: this.fieldConfig.name,
                    sourceId: this.object.id,
                    class: classParam
                }
            },
            store: 'HrefObject'
        });
    },

    getLayoutEdit: function () {
        console.log('fieldconfig:');
        console.log(this.fieldConfig);

        var show_trigger = false;
        if(typeof this.fieldConfig.showTrigger != "undefined") {   // compatible with older versions' configs that don't have this setting!
           if(this.fieldConfig.showTrigger) {
               show_trigger = true;
           }
        }

        var hrefTypeahead = {
            store: this.getHrefTypeaheadStore(),
            typeAhead: true,
            displayField: 'display',
            valueField: 'id',
            minChars: 2,
            hideTrigger: !show_trigger,
            name: this.fieldConfig.name,
        };

        if (this.fieldConfig.width) {
            hrefTypeahead.width = this.fieldConfig.width;
        } else {
            hrefTypeahead.width = 300;
        }
        hrefTypeahead.enableKeyEvents = true;
        hrefTypeahead.fieldCls        = "pimcore_droptarget_input";

        this.component = Ext.create('Ext.form.ComboBox', hrefTypeahead);

        if (this.data && this.data.path) {
            var hrefObject = Ext.create('HrefObject', Ext.clone(this.data));
            this.changeData(hrefObject, false, true);
        }

        this.component.on('keyup', function (e) {
            var pendingOperations = this.getStore().getProxy().pendingOperations;
            Ext.Object.eachValue(pendingOperations, function (pendingOperation) {
                pendingOperation.abort();
            });
        });

        this.component.on("render", function (el) {

            // add drop zone
            new Ext.dd.DropZone(el.getEl(), {
                reference: this,
                ddGroup: "element",
                getTargetFromEvent: function (e) {
                    return this.reference.component.getEl();
                },

                onNodeOver: function (target, dd, e, data) {

                    var record = data.records[0];
                    var data   = record.data;
                    if (this.dndAllowed(data)) {
                        return Ext.dd.DropZone.prototype.dropAllowed;
                    }
                    else {
                        return Ext.dd.DropZone.prototype.dropNotAllowed;
                    }

                }.bind(this),

                onNodeDrop: this.onNodeDrop.bind(this)
            });


            el.getEl().on("contextmenu", this.onContextMenu.bind(this));

        }.bind(this));

        this.component.on('change', function (combobox, newValue, oldValue) {
            var newRecord = combobox.findRecordByValue(newValue);
            if (newRecord) {
                if (newValue !== oldValue) {
                    this.dataChanged = true;
                }
                this.data.id      = newRecord.data.id;
                this.data.type    = newRecord.data.type;
                this.data.subtype = newRecord.data.subtype;
            }
            
            if (this.dataChanged && this.fieldConfig.listeners != undefined && {}.toString.call(this.fieldConfig.listeners.change) === '[object Function]'){
                this.fieldConfig.listeners.change(combobox, newValue, oldValue);
            }
            
        }.bind(this));

        var items = [this.component, {
            xtype: "button",
            iconCls: "pimcore_icon_edit",
            style: "margin-left: 5px",
            handler: this.openElement.bind(this)
        }, {
            xtype: "button",
            iconCls: "pimcore_icon_delete",
            style: "margin-left: 5px",
            handler: this.empty.bind(this)
        }, {
            xtype: "button",
            iconCls: "pimcore_icon_search",
            style: "margin-left: 5px",
            handler: this.openSearchEditor.bind(this)
        }];


        this.composite = Ext.create('Ext.form.FieldContainer', {
            fieldLabel: this.fieldConfig.title,
            layout: 'hbox',
            items: items,
            componentCls: "object_field",
            border: false,
            style: {
                padding: 0
            }
        });

        return this.composite;
    },

    getLayoutShowItems: function () {
        var items = [this.component];

        // In read-only mode we don`t need the pen if value is empty
        if (this.data.id) {
            items.push({
                xtype: "button",
                iconCls: "pimcore_icon_edit",
                handler: this.openElement.bind(this)
            })
        }
        return items;
    },

    getLayoutShow: function () {

        var hrefTypeahead = {
            fieldLabel: this.fieldConfig.title,
            name: this.fieldConfig.name,
            cls: "object_field",
            labelWidth: this.fieldConfig.labelWidth ? this.fieldConfig.labelWidth : 100
        };

        if (this.data) {
            if (this.data.path) {
                hrefTypeahead.value = this.data.path;
            }
        }

        if (this.fieldConfig.width) {
            hrefTypeahead.width = this.fieldConfig.width;
        } else {
            hrefTypeahead.width = 300;
        }
        hrefTypeahead.width    = hrefTypeahead.labelWidth + hrefTypeahead.width;
        hrefTypeahead.disabled = true;

        this.component = new Ext.form.TextField(hrefTypeahead);
        this.component.setValue(this.data.display)
        this.composite = Ext.create('Ext.form.FieldContainer', {
            layout: 'hbox',
            items: this.getLayoutShowItems(),
            componentCls: "object_field",
            border: false,
            style: {
                padding: 0
            }
        });

        return this.composite;

    },

    //
    /**
     * @desc We cannot just pass data because sometimes data has path and sometimes full path. Thanks Pimcore !
     *
     * @param {HrefObject} hrefObject
     * @param {boolean} dataChanged
     * @param {boolean} loadStore
     */
    changeData: function (hrefObject, dataChanged, loadStore) {
        if (loadStore === undefined) {
            loadStore = true
        }
        this.data.id      = hrefObject.get('id');
        this.data.type    = hrefObject.get('type');
        this.data.subtype = hrefObject.get('subtype');
        this.data.display = hrefObject.get('display');
        // Do not move this to the other if(!dataChanged), this is sets pimcore internals, dataChanged might be deprecated
        if (dataChanged) {
            this.dataChanged = true;
        }

        if (loadStore) {
            this.component.store.load({
                params: {valueIds: hrefObject.get('id')},
                callback: function () {
                    this.component.setValue(hrefObject.get('id'));
                    // Pimcore checks isDirty on this.component, Ext will return true if the original data (initially null)
                    // is not the same as the current id
                    if (!dataChanged) {
                        this.component.originalValue = hrefObject.get('id');
                    }
                    // this.component.autoSize();
                }.bind(this)
            });
        } else {

            this.component.store.add(this.data);
            this.component.setValue(hrefObject.get('id'));
            // Pimcore checks isDirty on this.component, Ext will return true if the original data (initially null)
            // is not the same as the current id
            if (!dataChanged) {
                this.component.originalValue = hrefObject.get('id');
            }
        }


        // this.component.setValue(path);
    },
    onNodeDrop: function (target, dd, e, dataParam) {
        var record = dataParam.records[0];
        var data   = record.data;
        if (this.dndAllowed(data)) {
            var hrefObject = Ext.create('HrefObject', {
                'id': data.id,
                'type': data.elementType,
                'subtype': data.type,
                'path': data.path
            });
            this.changeData(hrefObject, true, true);

            return true;
        } else {
            return false;
        }
    },
    /**
     * The right click action
     * Adds: Delete, Open, Search and Upload functionality
     * @param e
     */
    onContextMenu: function (e) {

        var menu = new Ext.menu.Menu();
        menu.add(new Ext.menu.Item({
            text: t('empty'),
            iconCls: "pimcore_icon_delete",
            handler: function (item) {
                item.parentMenu.destroy();

                this.empty();
            }.bind(this)
        }));

        menu.add(new Ext.menu.Item({
            text: t('open'),
            iconCls: "pimcore_icon_open",
            handler: function (item) {
                item.parentMenu.destroy();
                this.openElement();
            }.bind(this)
        }));

        menu.add(new Ext.menu.Item({
            text: t('search'),
            iconCls: "pimcore_icon_search",
            handler: function (item) {
                item.parentMenu.destroy();
                this.openSearchEditor();
            }.bind(this)
        }));


        menu.showAt(e.getXY());

        e.stopEvent();
    },

    openSearchEditor: function () {
        var allowedTypes    = [];
        var allowedSpecific = {};
        var allowedSubtypes = {};
        var i;

        if (this.fieldConfig.objectsAllowed) {
            allowedTypes.push("object");
            if (this.fieldConfig.classes != null && this.fieldConfig.classes.length > 0) {
                allowedSpecific.classes = [];
                allowedSubtypes.object  = ["object"];
                for (i = 0; i < this.fieldConfig.classes.length; i++) {
                    allowedSpecific.classes.push(this.fieldConfig.classes[i].classes);
                }
            } else {
                allowedSubtypes.object = ["object", "folder", "variant"];
            }
        }
        if (this.fieldConfig.assetsAllowed) {
            allowedTypes.push("asset");
            if (this.fieldConfig.assetTypes != null && this.fieldConfig.assetTypes.length > 0) {
                allowedSubtypes.asset = [];
                for (i = 0; i < this.fieldConfig.assetTypes.length; i++) {
                    allowedSubtypes.asset.push(this.fieldConfig.assetTypes[i].assetTypes);
                }
            }
        }
        if (this.fieldConfig.documentsAllowed) {
            allowedTypes.push("document");
            if (this.fieldConfig.documentTypes != null && this.fieldConfig.documentTypes.length > 0) {
                allowedSubtypes.document = [];
                for (i = 0; i < this.fieldConfig.documentTypes.length; i++) {
                    allowedSubtypes.document.push(this.fieldConfig.documentTypes[i].documentTypes);
                }
            }
        }

        pimcore.helpers.itemselector(false, this.addDataFromSelector.bind(this), {
            type: allowedTypes,
            subtype: allowedSubtypes,
            specific: allowedSpecific
        });
    },
    /**
     * Used to process data that comes from default search
     * @param data
     */
    addDataFromSelector: function (data) {
        var hrefObject = Ext.create('HrefObject', {
            'id': data.id,
            'type': data.type,
            'subtype': data.subtype,
            'path': data.fullpath
        });
        this.changeData(hrefObject, true, true);
    },
    /**
     * Provided open functionality for linked object
     */
    openElement: function () {
        if (this.data.id && this.data.type && this.data.subtype) {
            pimcore.helpers.openElement(this.data.id, this.data.type, this.data.subtype);
        }
    },
    /**
     * Clears the field
     */
    empty: function () {
        this.data        = {};
        this.dataChanged = true;
        this.component.setValue(null);
    },
    /**
     * @return {*} Field value
     */
    getValue: function () {
        return this.data;
    },
    /**
     * @return {*} Field name
     */
    getName: function () {
        return this.fieldConfig.name;
    },
    /**
     * Checks if drag and drop is allowed
     * @param data
     * @return {boolean}
     */
    dndAllowed: function (data) {
        var type      = data.elementType;
        var i;
        var subType;
        var isAllowed = false;
        if (type == "object" && this.fieldConfig.objectsAllowed) {

            var classname = data.className;

            isAllowed = false;
            if (this.fieldConfig.classes != null && this.fieldConfig.classes.length > 0) {
                for (i = 0; i < this.fieldConfig.classes.length; i++) {
                    if (this.fieldConfig.classes[i].classes == classname) {
                        isAllowed = true;
                        break;
                    }
                }
            } else {
                //no classes configured - allow all
                isAllowed = true;
            }


        } else if (type == "asset" && this.fieldConfig.assetsAllowed) {
            subType   = data.type;
            isAllowed = false;
            if (this.fieldConfig.assetTypes != null && this.fieldConfig.assetTypes.length > 0) {
                for (i = 0; i < this.fieldConfig.assetTypes.length; i++) {
                    if (this.fieldConfig.assetTypes[i].assetTypes == subType) {
                        isAllowed = true;
                        break;
                    }
                }
            } else {
                //no asset types configured - allow all
                isAllowed = true;
            }

        } else if (type == "document" && this.fieldConfig.documentsAllowed) {
            subType   = data.type;
            isAllowed = false;
            if (this.fieldConfig.documentTypes != null && this.fieldConfig.documentTypes.length > 0) {
                for (i = 0; i < this.fieldConfig.documentTypes.length; i++) {
                    if (this.fieldConfig.documentTypes[i].documentTypes == subType) {
                        isAllowed = true;
                        break;
                    }
                }
            } else {
                //no document types configured - allow all
                isAllowed = true;
            }
        }
        return isAllowed;
    },
    /**
     * Checks that field contains valid data when field is marked as mandatory
     * @return {boolean}
     */
    isInvalidMandatory: function () {
        return !this.data.id;

    }
});
