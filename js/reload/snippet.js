/**
 * reloadseoSnippet contains the snippet functionality.
 * @type {Object}
 */
var reloadseoSnippet = {
	/**
	 * The restrictions for the titles.
	 * @type {Object}
	 */
	titleRestrictions: {
		maxPixels: 497
	},

	/**
	 * The restrictions for the descriptions.
	 * @type {Object}
	 */
	descriptionRestrictions: {
		maxPixels: 920
	},

	/**
	 * Init prepares the use of the snippets.
	 * @return void
	 */
	init: function(type)
	{
		//Create an element for measuring the width of the text. This element is hidden by css.
		$reloadseo('body').append($reloadseo('<div id="snippet-test"></div>'));

		//Move the snippet into the form, the snippet is created in the seo.phtml template.
		if(type == 'product')
		{
			var form = $reloadseo('#product_edit_form');
		}
		else
		{
			var form = $reloadseo('#category_edit_form');
		}

		var snippet = $reloadseo('#reload-snippet');
		snippet.remove();
		form.prepend(snippet);
		snippet.show();

		//Bind the toggle button for hiding and showing the snippet.
		snippet.find('.toggle-snippet').on('click', function()
		{
			var buttonText = $reloadseo(this).find('span.button-text');
			var snippetContainer = snippet.find('.snippet-container').first();
			if(snippetContainer.is(':visible'))
			{
				snippetContainer.hide();
				buttonText.text('Show snippet');

				reloadseo.cookieHandler.set('showsnippet', 'false');
			}
			else
			{
				snippetContainer.show();
				buttonText.text('Hide snippet');

				reloadseo.cookieHandler.set('showsnippet', 'true');
			}
		});

		//Check the cookies if the snippet should be hidden or not on init.
		if(reloadseo.cookieHandler.get('showsnippet') === 'false')
		{	
			snippet.find('span.button-text').text('Show snippet');

			var snippetContainer = snippet.find('.snippet-container').first();
			snippetContainer.hide();
		}
		else
		{
			snippet.find('span.button-text').text('Hide snippet');
		}
	},

	/**
	 * updateSnippet recalculates the snippet.
	 * 
	 * @param  string text The text to check
	 * @param  string type Either title or description
	 * @return void
	 */
	updateSnippet: function(text, type)
	{
		//Obtain the test div.
		var testDiv = $reloadseo('#snippet-test');

		//Load the restrictions, the snippet container and result row in the table. Also prepare the test div with the correct class for the correct font sizes and such.
		if(type == 'title')
		{
			var restrictions = reloadseoSnippet.titleRestrictions;
			testDiv.removeClass('description-snippet');
			testDiv.addClass('title-snippet');
			var container = $reloadseo('#reload-snippet .title-snippet').first();
			var row = $reloadseo('#reload-snippet .snippet-summary .title-row').first();
		}
		else
		{
			var restrictions = reloadseoSnippet.descriptionRestrictions;
			testDiv.removeClass('title-snippet');
			testDiv.addClass('description-snippet');
			var container = $reloadseo('#reload-snippet .description-snippet').first();
			var row = $reloadseo('#reload-snippet .snippet-summary .description-row').first();
		}

		//Start updating recursively.
		this.update(row, container, text, restrictions, text, testDiv);
	},

	/**
	 * Update will calculate the width of the text and call itself if it is not finished.
	 * 
	 * @param Element row 
	 * @param Element container 
	 * @param string text     
	 * @param Object restrictions 
	 * @param string processedText 
	 * @param Element testDiv 
	 * @param float startWidth
	 * @return void
	 */
	update: function(row, container, text, restrictions, processedText, testDiv, startWidth)
	{
		var self = this;

		//Set the text in the test div and show the div (NB: it is at z-index: -10000, position absolute)
		testDiv.text(processedText);
		testDiv.show();

		//To force a new draw, we hide and then show the test div and wait for it to finish.
		testDiv.hide(0, function()
		{
			testDiv.show(0, function()
			{
				if(typeof startWidth === 'undefined')
				{
					//The start width was not calculated, lets do so.
					startWidth = testDiv.width();
				}

				if(testDiv.width() > restrictions.maxPixels)
				{
					//The text is to large, remove one character and start again.
					processedText = processedText.substring(0, processedText.length - 1);
					self.update(row, container, text, restrictions, processedText, testDiv, startWidth);
				}
				else
				{
					//Calculated the amount of characters truncated.
					var truncated = text.length - processedText.length;

					if(truncated > 0)
					{
						//The text was truncated, add dots like google would.
						processedText = processedText + '...';
					}

					//Hide the test div to be sure.
					testDiv.hide();

					//Set the processed text in the container.
					container.text(processedText);

					var td = row.find('td').first();
					
					//Set the requested text length
					td = td.next();
					td.text(text.length);

					//Set the result text length
					td = td.next();
					td.text(processedText.length);

					//Set the amount of characters truncated.
					td = td.next();
					td.css('color', (truncated > 0 ? '#ff0000' : '#000000'));
					td.text(truncated);

					//Set the requested width.
					td = td.next();
					td.text(startWidth);

					//Set the max allowed width.
					td = td.next();
					td.text(restrictions.maxPixels);

					//Set the remaining or over extended pixels.
					td = td.next();
					var remaining = restrictions.maxPixels - startWidth;
					td.css('color', (remaining > 0 ? '#049400' : '#ff0000'));
					td.text(remaining);
				}
			});
		});
	}
};