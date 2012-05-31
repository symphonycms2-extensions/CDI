
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
			
			jQuery('.manual-backup-overwrite').click(function(event) {
				jQuery('.cdi_export_action').attr('ref',(this.checked ? "overwrite" : ""));
			});
		
			jQuery('input[class*="_action"]').click(function(event) {
				Symphony.cdiExtension.processAction(this,event);
			});
		},
		
		//TODO: implement loading indicators for AJAX requests
		processAction: function(oElm,event) {
			var sAction = oElm.name;
			if(sAction == 'action[cdi_import]') { 
				oElm.form.submit(); 
			} else {
				jQuery('.cdiLoading').removeClass('cdiHidden');
				
				var sData = oElm.name + '=true&ref=' + oElm.getAttribute('ref');
				jQuery.post(Symphony.Context.get('root') + '/symphony/extension/cdi/actions/',sData, function(data) {
					// TODO: implement error handling for AJAX requests
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
									Symphony.cdiExtension.reshuffle(this);
									jQuery('.cdiLoading').addClass('cdiHidden');
								});
								break;
								
							case 'action[cdi_clear_restore]':
								jQuery(oElm).closest('div').fadeOut("slow",function() {
									var oRestoreUpload = jQuery('.cdiRestoreUpload');
									oRestoreUpload.removeAttr('style');

									var oTable = jQuery('.cdiRestore > table');
									var oEmptyTableCell = jQuery('.cdiRestore .cdiNoLastBackupCell');
									oEmptyTableCell.removeAttr('style');
									
									oTable.empty();
									oTable.append(oEmptyTableCell);
									
									jQuery(this).detach();
									jQuery('.cdiLoading').addClass('cdiHidden');
								});
								break;
								
							case 'action[cdi_export]':
								var oResult = jQuery('<div />').html(data.result).text();
								jQuery('.cdiRestore').fadeOut('slow', function() {
									oResult = jQuery('.cdiRestore').replaceWith(oResult);
									jQuery('.cdiRestore').find('input').click(function(event) {
										Symphony.cdiExtension.processAction(this,event);
									});
									jQuery('.cdiLoading').addClass('cdiHidden');
								});
								break;
								
							case 'action[cdi_restore]':
								jQuery(oElm).fadeOut('slow',function() {
									jQuery(this).replaceWith('<span style="color: green;">Restored</span>');
									jQuery('.cdiLoading').addClass('cdiHidden');
								});
								break;

						}
					} else {
						jQuery('.cdiLoading').html("An error occurred while processing your request. Please refer to the Symphony log files for more information.");
					}
				});
			}
		},
		
		reshuffle: function(oElm) {
			oElm = jQuery(oElm);
			
			var cdiNode = oElm.closest('div.cdi, div.db_sync');
			var cdiMode =   (cdiNode.hasClass('CdiMaster') ? 'CdiMaster' : 
							(cdiNode.hasClass('CdiSlave') ? 'CdiSlave' : 
							(cdiNode.hasClass('DBSyncMaster') ? 'DBSyncMaster' :
							(cdiNode.hasClass('DBSyncSlave') ? 'DBSyncSlave' : 'unknown'))));
			
			if(oElm.hasClass('cdiClear')) {
			
				switch(cdiMode) {
					case 'CdiMaster': 
						jQuery(cdiNode).find('.cdiDownload').detach();
						var oExport = jQuery(cdiNode).find('.cdiExport');
						var oRestore = jQuery(cdiNode).find('.cdiRestore');
						oExport.before(oRestore);
						oElm.replaceWith(oExport);
						break;
					case 'DBSyncMaster':
						jQuery(cdiNode).find('.cdiDownload').detach();
						var oInstance = jQuery(cdiNode).find('.instanceMode');
						oElm.replaceWith(oInstance);
						
						var oRestore = jQuery(cdiNode).find('.cdiRestore');
						var oFooter = jQuery(cdiNode).find('.cdiFooter');
						oFooter.append(oRestore);
						
						break;
					case 'CdiSlave':
					case 'DBSyncSlave':
						var oRestore = jQuery(cdiNode).find('.cdiRestore');
						var oFooter = jQuery(cdiNode).find('.cdiFooter');
						oFooter.append(oRestore);
						oElm.detach();
						break;
				}
			}
		}
	}
})(jQuery.noConflict());

jQuery(document).ready(function() {
	Symphony.cdiExtension.doBind();
});