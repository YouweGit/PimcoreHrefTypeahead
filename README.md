# Href typeahead field type
Add typeahead functionality to href fields using a modified default search functionality
I temporary made documents and assets read only field in field configurator to prevent users to
select assets as a valid link at static/js/pimcore/object/classes/data/hrefTypeahead.js, but i didn't want 
to remove it because maybe we will add functionality to support assets and document
Also i added validation to prevent users to select more then one class

### Files 
<dl>
  <dt><strong>static/js/pimcore/object/classes/data/hrefTypeahead.js</strong></dt>
  <dd>An exact copy of default href</dd>
  
  <dt><strong>static/js/pimcore/object/tags/hrefTypeahead.js</strong></dt>
  <dd>An exact copy of default href, with the change that textbox is now a combo with autocomplete provided by SearchController::findAction</dd>
    
  <dt><strong>models/Pimcore/Model/Object/ClassDefinition/Data/HrefTypeahead.php</strong></dt>
  <dd>An exact copy of default href</dd>
  
  <dt><strong>HrefTypeahead/controllers/SearchController.php</strong></dt>
  <dd>A controller containing autocomplete source</dd>
</dl>

### Limitations
* It only supports one object linked in href
