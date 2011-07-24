
if(!window.Symphony) { var Symphony = {}; }

(function($) {

	Symphony.cdiExtension = {

		doBind: function() {
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
				Symphony.cdiExtension.processAction(this,event);
			});
		},
		
		processAction: function(oElm,event) {
			var sAction = oElm.name;
			if(sAction == 'action[cdi_import]') { 
				oElm.form.submit(); 
			} else {
				var sData = oElm.name + '=true&ref=' + oElm.getAttribute('ref');
				jQuery.post(Symphony.WEBSITE + '/symphony/extension/cdi/save/',sData, function(data) {
					if(data.status == 'success') {
						switch(sAction) {
							case 'action[cdi_clear]':
								jQuery(oElm).closest('.cdiClear').fadeOut("slow", function() {
									// Update Query Log table
									var oTable = jQuery('.cdiLastQueries > table');
									var oEmptyTableCell = jQuery('.cdiLastQueries .cdiNoLastQueriesCell');
									oEmptyTableCell.removeAttr('style');
									
									oTable.empty();
									oTable.append(oEmptyTableCell);
									
									// Update Import method
									jQuery('.cdiImport').detach();
									jQuery('.cdiImportFile').fadeIn('slow');
	
									// Remove the clear option
									jQuery(this).detach();
								});
								break;
								
							case 'action[cdi_clear_restore]':
								jQuery(oElm).closest('div').fadeOut("slow",function() {
									var oTable = jQuery('.cdiRestore > table');
									var oEmptyTableCell = jQuery('.cdiRestore .cdiNoLastBackupCell');
									oEmptyTableCell.removeAttr('style');
									
									oTable.empty();
									oTable.append(oEmptyTableCell);
									
									jQuery(this).detach();
								});
								break;
								
							case 'action[cdi_export]':
								var oResult = jQuery('<div />').html(data.result).text();
								jQuery('.cdiRestore').fadeOut('slow', function() {
									oResult = jQuery('.cdiRestore').replaceWith(oResult);
									jQuery('.cdiRestore').find('input').click(function(event) {
										Symphony.cdiExtension.processAction(this,event);
									});
								});
								break;
						}
					}
				});
			}
		}
	}
})(jQuery.noConflict());

jQuery(document).ready(function() {
	Symphony.cdiExtension.doBind();
});