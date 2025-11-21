/**
 * GitHub Push 管理画面用JavaScript
 */

(function($) {
	'use strict';
	
	$(document).ready(function() {
		
		// プラグインスラッグの選択
		$('#plugin_slug_select').on('change', function() {
			var selectedSlug = $(this).val();
			if (selectedSlug) {
				$('#plugin_slug').val(selectedSlug);
			}
		});
		
		// テーマスラッグの選択
		$('#theme_slug_select').on('change', function() {
			var selectedSlug = $(this).val();
			if (selectedSlug) {
				$('#theme_slug').val(selectedSlug);
			}
		});
		
		// プラグイン/テーマ切り替え
		function toggleComponentFields() {
			var type = $('input[name="component_type"]:checked').val() || 'plugin';
			var isTheme = type === 'theme';
			
			if (isTheme) {
				$('.github-push-field--theme').show();
				$('.github-push-field--plugin').hide();
			} else {
				$('.github-push-field--theme').hide();
				$('.github-push-field--plugin').show();
			}
			
			$('#plugin_slug').prop('required', !isTheme);
			$('#theme_slug').prop('required', isTheme);
		}
		
		$('input[name="component_type"]').on('change', toggleComponentFields);
		toggleComponentFields();
		
		// 更新チェック
		$(document).on('click', '.check-update', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var pluginId = $button.data('plugin-id');
			
			if (!pluginId) {
				return;
			}
			
			$button.prop('disabled', true).text(githubPush.i18n.checking);
			
			$.ajax({
				url: githubPush.ajaxUrl,
				type: 'POST',
				data: {
					action: 'github_push_check_update',
					nonce: githubPush.nonce,
					plugin_id: pluginId
				},
				success: function(response) {
					if (response.success) {
						// エラーメッセージがある場合は表示
						if (response.data.error) {
							showModal('エラー: ' + response.data.error, 'error');
						} else {
							var message = response.data.update_available ? 
								'更新が利用可能です: ' + response.data.latest_version : 
								'更新はありません';
							showModal(message);
							
							// 更新が利用可能な場合は、ページをリロードして更新ボタンを表示
							if (response.data.update_available) {
								// モーダルを閉じた後にリロード
								var modalClosed = false;
								var reloadAfterClose = function() {
									if (!modalClosed) {
										modalClosed = true;
										setTimeout(function() {
											location.reload();
										}, 100);
									}
								};
								
								// 閉じるボタンクリック時
								$(document).one('click', '.github-push-modal-close', function() {
									reloadAfterClose();
								});
								
								// モーダル外クリック時
								$(document).one('click', '#github-push-modal', function(e) {
									if ($(e.target).hasClass('github-push-modal')) {
										reloadAfterClose();
									}
								});
							}
						}
					} else {
						// エラーが返された場合
						var errorMessage = response.data && response.data.message ? response.data.message : 'エラーが発生しました';
						showModal('エラー: ' + errorMessage, 'error');
					}
				},
				error: function() {
					showModal('エラーが発生しました', 'error');
				},
				complete: function() {
					$button.prop('disabled', false).text('更新チェック');
				}
			});
		});
		
		// プラグイン更新
		$(document).on('click', '.update-plugin', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var pluginId = $button.data('plugin-id');
			
			if (!pluginId) {
				return;
			}
			
			if (!confirm('選択した項目を更新しますか？')) {
				return;
			}
			
			$button.prop('disabled', true).text(githubPush.i18n.updating);
			
			$.ajax({
				url: githubPush.ajaxUrl,
				type: 'POST',
				data: {
					action: 'github_push_update_plugin',
					nonce: githubPush.nonce,
					plugin_id: pluginId
				},
				success: function(response) {
					if (response.success) {
						showModal(response.data.message || githubPush.i18n.success);
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						showModal('エラー: ' + response.data.message, 'error');
						$button.prop('disabled', false).text('更新');
					}
				},
				error: function() {
					showModal('エラーが発生しました', 'error');
					$button.prop('disabled', false).text('更新');
				}
			});
		});
		
		// ロールバック（ログから）
		$(document).on('click', '.rollback-from-log', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var pluginId = $button.data('plugin-id');
			var version = $button.data('version');
			var backupPath = $button.data('backup-path');
			
			if (!pluginId) {
				return;
			}
			
			var confirmMessage = version 
				? 'バージョン ' + version + ' にロールバックしますか？'
				: githubPush.i18n.confirmRollback;
			
			if (!confirm(confirmMessage)) {
				return;
			}
			
			$button.prop('disabled', true).text('ロールバック中...');
			
			$.ajax({
				url: githubPush.ajaxUrl,
				type: 'POST',
				data: {
					action: 'github_push_rollback',
					nonce: githubPush.nonce,
					plugin_id: pluginId,
					backup_path: backupPath,
					version: version
				},
				success: function(response) {
					if (response.success) {
						showModal(response.data.message || 'ロールバックが完了しました');
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						showModal('エラー: ' + response.data.message, 'error');
						$button.prop('disabled', false).text('このバージョンに戻す');
					}
				},
				error: function() {
					showModal('エラーが発生しました', 'error');
					$button.prop('disabled', false).text('このバージョンに戻す');
				}
			});
		});
		
		// モーダルを閉じる
		$(document).on('click', '.github-push-modal-close', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$('#github-push-modal').hide();
		});
		
		// モーダル外をクリックで閉じる
		$(document).on('click', '#github-push-modal', function(e) {
			if ($(e.target).hasClass('github-push-modal')) {
				$('#github-push-modal').hide();
			}
		});
		
		// モーダルコンテンツ内のクリックは閉じないようにする
		$(document).on('click', '.github-push-modal-content', function(e) {
			e.stopPropagation();
		});
		
		// モーダルを表示
		function showModal(message, type) {
			var $modal = $('#github-push-modal');
			var $message = $modal.find('.github-push-modal-message');
			
			$message.text(message);
			
			if (type === 'error') {
				$message.css('color', '#dc3232');
			} else {
				$message.css('color', '');
			}
			
			$modal.show();
		}
		
	});
	
})(jQuery);

