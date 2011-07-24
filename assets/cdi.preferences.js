
jQuery(document).ready(function () {
	
	jQuery('.cdi-mode').bind('change keyup keydown', function(event) {
		var sValue = this.value;
		jQuery(this).closest('div').nextAll().empty();
		jQuery('.cdiModeRestart').removeAttr('style');
		jQuery(this).unbind(event);
	});
	
	jQuery('.instance-mode').click(function(event) {
		var oParent = jQuery(this).closest('.cdi, .db_sync');
		var oElm = jQuery(this).closest('div').detach();
		oParent.before(oElm);
		oParent.detach();

		jQuery('.cdiInstanceRestart').removeAttr('style');
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

	jQuery('input[class*="_action"]').click(function(event) {
		var oThis = this;
		var sAction = this.name;
		if(sAction == 'action[cdi_import]') { 
			this.form.submit(); 
		} else {
			var sData = this.name + '=true&ref=' + this.getAttribute('ref');
			jQuery.post(Symphony.WEBSITE + '/symphony/extension/cdi/save/',sData, function(data) {
				if(data.status == 'success') {
					switch(sAction) {
						case 'action[cdi_clear]':
							var oTable = jQuery('.cdiLastQueries > table');
							var oEmptyTableCell = jQuery('.cdiLastQueries .cdiNoLastQueriesCell');
							oEmptyTableCell.removeAttr('style');
							
							oTable.empty();
							oTable.append(oEmptyTableCell);
							
							jQuery(oThis).closest('.cdiClear').fadeOut("slow", function() {
								jQuery(this).detach();
							});
							break;
							
						case 'action[cdi_export]':
							var oResult = jQuery('<div />').html(data.result).text();
							jQuery('.cdiRestore').fadeOut('slow', function() {
								oResult = jQuery('.cdiRestore').replaceWith(oResult);
							});
							break;
					}
				}
			});
		}
	});
});