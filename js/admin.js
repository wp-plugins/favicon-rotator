/**
 * Admin JS
 * @package Favicon Rotator
 */

/* Prototypes */

if ( !Array.indexOf ) {
	Array.prototype.indexOf = function(val) {
		for ( var x = 0; x < this.length; x++ ) {
			if ( this[x] == val )
				return x;
		}
		return -1;
	}
}

if ( typeof(fvrt) == 'undefined' || typeof(fvrt) != 'object' )
	fvrt = {};
(function($) {
	/**
	 * Initialization routines
	 */
	fvrt['init'] = function() {
		this.setupActions();
	};
	
	fvrt['sel'] = {
		list: '#fv_list',
		item: '.fv_item',
		image: '.icon',
		details: '.details',
		name: '.name',
		itemTemplate: '#fv_item_temp',
		remove: '.remove',
		field: '#fv_ids'
	};
	
	/**
	 * Setup event actions for icon elements
	 */
	fvrt['setupActions'] = function() {
		//Get remove links on page
		var t = this;
		$(this.buildSelector('list', 'item', 'remove')).live('click', function() {
			t.removeItem(this);
			return false;
		});
	};
	
	fvrt['buildSelector'] = function() {
		var sel = [];
		for ( var i = 0; i < arguments.length; i++ ) {
			if ( arguments[i] in this.sel ) {
				sel.push(this.sel[arguments[i]]);
			}
		}
		return sel.length ? sel.join(' ') : '';
	};
	
	/**
	 * Retrieve IDs hidden field
	 * @return object IDs field
	 */
	fvrt['getField'] = function() {
		return $(this.sel.field);
	};
	
	/**
	 * Gets IDs of icons
	 * @return array Icon IDs
	 */
	fvrt['getIds'] = function() {
		return $(this.getField()).val().split(',');
	};
	
	/**
	 * Check if ID is already added
	 * @param int itemId Icon ID
	 * @return bool TRUE if icon is already added
	 */
	fvrt['hasId'] = function(itemId) {
		return ( this.getIds().indexOf(itemId) != -1 ) ? true : false; 
	};
	
	/**
	 * Sets list of Icon IDs
	 * @param array Icon IDs
	 */
	fvrt['setIds'] = function(ids) {
		$(this.getField()).val(ids.join(','));
	};
	
	/**
	 * Add ID to IDs field
	 * @param int itemId Attachment ID to add
	 */
	fvrt['addId'] = function(itemId) {
		if ( !this.hasId(itemId) ) {
			var vals = this.getIds();
			vals.push(itemId);
			this.setIds(vals);
		}
	};
	
	/**
	 * Remove ID from IDs field
	 * @param int itemId Icon ID to remove
	 */
	fvrt['removeId'] = function(itemId) {
		var vals = this.getIds();
		var idx = vals.indexOf(itemId);
		if ( idx != -1 )
			vals.splice(idx, 1);
		this.setIds(vals);
	};
	
	/**
	 * Get Icon ID of specified item
	 * @param {Object} el Node element
	 */
	fvrt['getItemId'] = function(el) {
		var id = '';
		var parts = el.id.split('_');
		if ( parts.length )
			id = parts[parts.length - 1];
		return id;
	};
	
	/**
	 * Add item to list
	 * @param object args Icon properties
	 *   id: Attachment ID
	 *   name: File name
	 *   url: Attachment URL
	 */
	fvrt['addItem'] = function(args) {
		if (typeof args == 'object' && args.id && args.name && args.url && !this.hasId(args.id)) {
			//Build new item
			var item = $(this.sel.itemTemplate).clone();
			$(item).attr('id', 'fv_item_' + args.id);
			$(item).find(this.sel.image).attr('src', args.url);
			$(item).find(this.sel.name).text(args.name);
			$(item).find(this.sel.remove).attr('id', 'fv_id_' + args.id);

			//Add element to container
			$(item).appendTo(this.sel.list)

			//Add element ID to list
			this.addId(args.id);
		}
	};
	
	/**
	 * Remove item from list
	 * @param {Object} el Node to remove
	 */
	fvrt['removeItem'] = function(el) {
		//Get ID of item
		var id = this.getItemId(el);
		//Remove item
		$(el).parents('.fv_item').remove();
		//Remove ID from field
		this.removeId(id);
	};
	
	//Initialize on document load
	$(document).ready(function() {fvrt.init();});
}) (jQuery);