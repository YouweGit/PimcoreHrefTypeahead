/**
 * A placeholder to make it easy to work with current object
 */

Ext.define('HrefObject', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'id', type: 'int'},
        {name: 'index', type: 'int', defaultValue: 1},
        {name: 'dest_id', type: 'int'},
        {name: 'display', type: 'string'},
        {name: 'type', type: 'string'},
        {name: 'subtype', type: 'string'},
        {name: 'path', type: 'string'}
    ]
});