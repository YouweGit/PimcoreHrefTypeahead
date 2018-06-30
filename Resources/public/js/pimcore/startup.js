pimcore.registerNS("pimcore.plugin.PimcoreHrefTypeaheadBundle");

pimcore.plugin.PimcoreHrefTypeaheadBundle = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return "pimcore.plugin.PimcoreHrefTypeaheadBundle";
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function (params, broker) {
        // alert("PimcoreHrefTypeaheadBundle ready!");
    }
});

var PimcoreHrefTypeaheadBundlePlugin = new pimcore.plugin.PimcoreHrefTypeaheadBundle();
