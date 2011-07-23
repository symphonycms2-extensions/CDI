
jQuery(document).ready(function () {
	
	jQuery('.cdi-mode').bind('change keyup keydown', function() {
		var sValue = this.value;
		jQuery(this).closest('div').nextAll().each(function(index,oElm) {
			if(jQuery(oElm).hasClass(sValue)) {
				jQuery(this).css('display','');
			} else {
				jQuery(this).css('display','none');
			}
		});
	});
	
	jQuery('.instance-mode').click(function(event) {
		var oParent = jQuery(this).closest('.cdi');
		var oElm = jQuery(this).closest('div').detach();
		oParent.before(oElm);
		oParent.detach();

		oElm.addClass('cdi');
		jQuery('.restart').removeAttr('style');
		jQuery(this).unbind(event);
	});
	
	jQuery('.backup-enabled').click(function() {
		if(this.checked) {
			jQuery('.backup-overwrite, .restore-enabled, .maintenance-enabled').removeAttr('disabled');
		} else {
			jQuery('.backup-overwrite, .restore-enabled, .maintenance-enabled').attr('disabled','disabled');
		}
	});
	
	jQuery('.backup-enabled,.backup-overwrite,.restore-enabled,.maintenance-enabled').click(function() {
		var chk = this.checked;
		jQuery('.' + this.className).each(function(index,oElm) {
			oElm.checked = chk;
		});
	});

	jQuery('input[class*="_action"]').click(function() {
		var sAction = this.name;
		var sData = this.name + '=true&ref=' + this.getAttribute('ref');
		jQuery.post(Symphony.WEBSITE + '/symphony/extension/cdi/save/',sData, function(data) {
			if(data.status == 'success') {
				switch(sAction) {
					case 'action[cdi_clear]':
						var oTable = jQuery('.lastQueries > table');
						var oEmptyTableCell = jQuery('.lastQueries .noLastQueriesCell');
						oEmptyTableCell.removeAttr('style');
						
						oTable.empty();
						oTable.append(oEmptyTableCell);
						break;
				}
			}
		});
	});
});