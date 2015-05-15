var reloadseo = {

	/**
	 * The reference of the key up timer.
	 * @type int
	 */
	keyupTimer: null,

	/**
	 * The reference of the ajax request.
	 * @type int
	 */
	currentRequest: null,

	/**
	 * The type of the current action, product or category
	 * @type string
	 */
	type: null,

	/**
	 * The url which should be called when we want to get results.
	 * @type string
	 */
	url: null,

	/**
	 * The variables that will be set from the template.
	 * @type {Object}
	 */
	vars: {},

	data: {},

	/**
	 * init initializes everything
	 * @return void
	 */
	init: function()
	{
		var self = this;

		if(self.cookieHandler.get('showseo') === 'false')
		{
			$reloadseo('body').addClass('hide-seo-results');
			$reloadseo('.seo-toggle-button').html('Show');
		}

		$reloadseo('.seo-toggle-button').off('click');
		$reloadseo('.seo-toggle-button').on('click', self.toggle);

		//Bind the tinymceBeforeSetContent to add the seo results to the wysiwyg when it's opened.
		varienGlobalEvents.attachEventHandler('tinymceBeforeSetContent', function()
		{ 
			reloadseo.copyResultsToWysiwyg();
			$reloadseo('.seo-toggle-button').off('click');
			$reloadseo('.seo-toggle-button').on('click', self.toggle);
		});

		//Bind the showTab event to try and listen to the file upload.
		varienGlobalEvents.attachEventHandler('showTab', function()
		{
			if(typeof media_gallery_contentJsObject !== 'undefined')
			{
				if(typeof media_gallery_contentJsObject.uploader !== 'undefined' && media_gallery_contentJsObject.uploader != null)
				{
					//Wait half a second to let the script initialze the media gallery uploader.
					setTimeout(function()
					{
						if(typeof media_gallery_contentJsObject.uploader.uploader !== 'undefined' && media_gallery_contentJsObject.uploader.uploader != null)
						{
							//Bind the file upload complete event.
							media_gallery_contentJsObject.uploader.uploader.addEventListener('complete', function() 
							{ 
								//Trigger the reload.
								$reloadseo(".track-seo").first().trigger('change');
							});
						}
					}, 500);
				}
			}
		});

		if($reloadseo('#media_gallery_content_grid').length)
		{
			//Bid all input fields in the images grid to reload the seo results on change.
			$reloadseo('#media_gallery_content_grid').on('keyup', 'input', function() 
			{ 
				self.updateSeo();
			});
		}

		if(self.type === 'product')
		{
			var nameRow = $reloadseo('#name').parents('tr').first();
		}
		else
		{
			var nameRow = $reloadseo("input[name='general[name]']").first().parents('tr').first();
		}

		var row = $reloadseo('<tr><td class="label"><label for="reload_seo_keywords">' + self.vars.translations.seo_keywords + '</label></td><td class="value"><input id="reload_seo_keywords" name="reload_seo_keywords" class="track-seo" value="' + self.vars.current_keywords + '" type="text"></td><td class="scope-label"></td></tr>');
		row.find('input').first().data('track-field', 'keywords');

		if(self.vars.type === 'product') {
			$reloadseo("[name='product[sku]']").addClass('track-seo').data('track-field', 'sku');
		}

		row.insertAfter(nameRow);

		//Loop over the field mapping and add the class track-seo to all fields which belong to the field mapping.
		//Put the external field name in the data-track-field attribute.
		$reloadseo.each(self.vars.field_mapping, function(internal, external)
		{
			if(self.vars.type === 'product') {
				$reloadseo("[name='product[" + internal + "]']").addClass('track-seo').data('track-field', external).data('track-field-internal', internal);
			} else {
				$reloadseo("[name='general[" + internal + "]']").addClass('track-seo').data('track-field', external).data('track-field-internal', internal);
			}
		});

		//Bind the key up listener to all fields.
		$reloadseo(".track-seo").bind('keyup', function()
		{
			//On key up reset the timeout.
			clearTimeout(self.keyupTimer);
			self.keyupTimer = setTimeout(function()
			{
				//When the timeout is over we want to update the seo.
				self.updateSeo();
			}, 300);
		});

		//Bind the change listener to all fields.
		$reloadseo(".track-seo").bind('change', function()
		{
			//Clear the key up timeout and update the seo directly.
			clearTimeout(self.keyupTimer);
			self.updateSeo();
		});

		//Collect the initial data.
		self.data = self.collectData();

		$reloadseo('#reload_seo_keywords').select2({
			tags: true,
			placeholder: '',
			tokenSeparators: [","],
			minimumInputLength: 1,
			maximumSelectionLength: 2,
			initSelection : function (element, callback) {
				var asString = $reloadseo('#reload_seo_keywords').val();
				var data = [];
				$reloadseo.each(asString.split(','), function(k, v)
				{
					data.push({id: v, text: v});
				});		        
		    callback(data);
		    },
			ajax: {
				url: "https://suggestqueries.google.com/complete/search?callback=?",
				dataType: 'jsonp',
				data: function (term, page) {
		            return {q: term, hl: 'en', client: 'firefox' };
		        },
		        results: function (data, page) { 
		        	var items = {};

		        	$reloadseo.each(data[1], function(k, v)
		        	{
		        		items[v.toLowerCase()] = v.toLowerCase();
		        		
		        	});
		        	
		        	var input = $reloadseo('#reload_seo_keywords').data().select2.search.val();
		        	$reloadseo.each(input.split(','), function(k, v)
					{
							items[v.toLowerCase()] = v.toLowerCase();
					});

		        	var results = [];
		        	results.push({id: input.toLowerCase(), text: input.toLowerCase()});
		        	$reloadseo.each(items, function(k, v)
		        	{
		        		results.push({id: v, text: v});
		        	});

		            return { results: results };
		        },
			}
		});
	},

	toggle: function()
	{
		if($reloadseo('body').hasClass('hide-seo-results'))
		{
			$reloadseo('body').removeClass('hide-seo-results');
			$reloadseo('.seo-toggle-button').html('Hide');
			reloadseo.cookieHandler.set('showseo', 'true');
		}
		else
		{
			$reloadseo('body').addClass('hide-seo-results');
			$reloadseo('.seo-toggle-button').html('Show');
			reloadseo.cookieHandler.set('showseo', 'false');
		}
	},

	/**
	 * collectData collects all data from the input fields.
	 * @return Array
	 */
	collectData: function(replaceData)
	{
		var data = {};
		//Loop over all fields and get the value.
		$reloadseo('.track-seo').each(function()
		{
			if(typeof $reloadseo(this).data('track-field') !== 'undefined')
			{
				data[$reloadseo(this).data('track-field')] = $reloadseo(this).val();
			}

			if(typeof replaceData !== 'undefined' && replaceData.field == $reloadseo(this).data('track-field-internal'))
			{
				data[$reloadseo(this).data('track-field')] = replaceData.content;
			}
		});

		//Collect the image urls and labels.
		var images = {};
		if(typeof media_gallery_contentJsObject !== 'undefined' && typeof media_gallery_contentJsObject.images !== 'undefined')
		{
			$reloadseo.each(media_gallery_contentJsObject.images, function(k, v)
			{
				images[k] = {
					url: v.url,
					name: v.label
				};
			});
		}
		data['images'] = images;

		return data;
	},

	/**
	 * updateSeo makes an AJAX call to get the seo score and rules.
	 * @return void
	 */
	updateSeo: function(replaceData)
	{
		var self = this;

		//Collect the data and check if something changed.
		var data = self.collectData(replaceData);

		var changed = false;
		$reloadseo.each(data, function(k, v)
		{
			if(self.data[k] != v)
			{
				changed = true;
			}
		});

		if(changed)
		{
			//Changes were made, abort the current request.
			if(self.currentRequest !== null)
			{
				self.currentRequest.abort();
			}
			self.data = data;

			//Obtain the form key.
			data.form_key = self.vars.form_key;

			//Make the AJAX request.
			self.currentRequest = $reloadseo.ajax({
				url: self.url,
				type: 'post',
				data: data,
				dataType: 'json'
			}).done(function(data)
			{
				if(data != null && 'score' in data)
				{
					//Only do something when the data was valid.
					var elements = $reloadseo('.reload-seo-scores');

					elements.each(function()
					{
						var element = jQuery(this);
						//Set the basic score value and color.
						element.find('.base-score>span.seo-score').first().text(data.score.toString());
						element.find('.base-score>span.seo-score').first().css('background-color', data.color);

						//Remove all current rules.
						element.find('.seo-rules>table>tbody>tr.seo-rule').remove();
						var tbodyElement = element.find('.seo-rules>table>tbody').first();
						$reloadseo.each(data.rules, function(k, v)
						{
							//Loop over the rules and create html rows.
							tbodyElement.append($reloadseo("<tr class='seo-rule'><td class='indicator'><div class='indicator-dot' style='background-color: " + v.color + ";'></div></td><td>" + v.title + "</td></tr>"));
						});
					});
				}
			});
		}
	},

	/**
	 * copyResultsToWysiwyg copies the result html into the wysiwyg editor.
	 * 
	 * @return void
	 */
	copyResultsToWysiwyg: function()
	{
		var self = this;

		//Get the wysiwyg element.
		var wysiwyg = $reloadseo('#catalog-wysiwyg-editor');

		//Only execute when the wysiwyg does not contain the seo results yet.
		if(!wysiwyg.hasClass('contains-seo-results'))
		{
			//Clone the result element.
			var element = $reloadseo('#reload-seo-scores').clone();
			element.attr('id', element.attr('id') + '-wysiwyg');

			//Find the textarea in the editor.
			var textarea = wysiwyg.find('textarea').first();
			if(textarea.length)
			{
				//Get the container.
				var container = textarea.parents('span.field-row').first();
				if(container.length)
				{
					//Append the results element to the container.
					container.append(element);
					container.addClass('seo-results-container');
					//Set the class so the results won't be added twice.
					wysiwyg.addClass('contains-seo-results');

					//Bind the tinyMCE onChange event to update when the values changed.
					tinyMCE.activeEditor.onChange.add(function(e) 
					{ 
						//Clear the key up timeout and update the seo directly.
						clearTimeout(self.keyupTimer);

						var fieldName = e.editorId.replace('_editor', '');
						var content = e.getContent();

						//Update the seo results and add custom content from the editor.
						self.updateSeo({field: fieldName, content: content});
					});

					tinyMCE.activeEditor.onKeyUp.add(function(e) 
					{ 
						//On key up reset the timeout.
						clearTimeout(self.keyupTimer);
						self.keyupTimer = setTimeout(function()
						{
							var fieldName = e.editorId.replace('_editor', '');
							var content = e.getContent();

							//When the timeout is over we want to update the seo.
							self.updateSeo({field: fieldName, content: content});
						}, 300);
					});
				}
			}
		}
	},

	cookieHandler: (function()
	{
	    var self = {};
	    
	    self.cookies = {};
	    
	    self.init = function()
	    {
	        self.cookies = {};
	        
	        var cookieString = document.cookie;
	        var cookieData = cookieString.split("; ");
	        
	        if(typeof cookieData != 'undefined')
	        {
	            for(var i in cookieData)
	            {
	                if(typeof cookieData[i] == 'string')
	                {
	                    var data = cookieData[i].split("=");
	                    if(typeof data != 'undefined' && data.length == 2)
	                    {
	                        self.cookies[data[0]] = data[1];
	                    }
	                }
	            }
	        }
	    };
	    
	    self.init();

	    self.get = function(name)
	    {
	        if(name in this.cookies)
	            return this.cookies[name];
	        
	        return null;
	    };

	    self.set = function(name, value)
	    {
	        document.cookie = name + '=' + value + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/'
	        self.init();
	    };
	    
	    return self;
	}(this))
};