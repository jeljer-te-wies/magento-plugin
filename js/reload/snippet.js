var reloadseoSnippet = {
	titleRestrictions: {
		maxPixels: 497
	},

	descriptionRestrictions: {
		maxPixels: 920
	},

	init: function()
	{
		$reloadseo('body').append($reloadseo('<div id="snippet-test"></div>'));

		var productForm = $reloadseo('#product_edit_form');
		var snippet = $reloadseo('#reload-snippet');
		snippet.remove();
		productForm.prepend(snippet);
		snippet.show();

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

	updateSnippet: function(text, type)
	{
		var testDiv = $reloadseo('#snippet-test');

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

		this.update(row, container, text, restrictions, text, testDiv);
	},

	update: function(row, container, text, restrictions, processedText, testDiv, startWidth)
	{
		var self = this;
		testDiv.text(processedText);
		testDiv.show();
		testDiv.hide(0, function()
		{
			testDiv.show(0, function()
			{
				if(typeof startWidth === 'undefined')
				{
					startWidth = testDiv.width();
				}

				if(testDiv.width() > restrictions.maxPixels)
				{
					processedText = processedText.substring(0, processedText.length - 1);
					self.update(row, container, text, restrictions, processedText, testDiv, startWidth);
				}
				else
				{
					var truncated = text.length - processedText.length;

					if(truncated > 0)
					{
						processedText = processedText + '...';
					}

					testDiv.hide();
					container.text(processedText);

					var td = row.find('td').first();
					
					td = td.next();
					td.text(text.length);

					td = td.next();
					td.text(processedText.length);

					td = td.next();
					td.css('color', (truncated > 0 ? '#ff0000' : '#000000'));
					td.text(truncated);

					td = td.next();
					td.text(startWidth);

					td = td.next();
					td.text(restrictions.maxPixels);

					td = td.next();
					var remaining = restrictions.maxPixels - startWidth;
					td.css('color', (remaining > 0 ? '#049400' : '#ff0000'));
					td.text(remaining);
				}
			});
		});
	}
};