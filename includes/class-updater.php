<?php
/**
 * プラグイン更新処理クラス
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * プラグイン更新処理クラス
 */
class GitHub_Push_Updater {
	
	/**
	 * インスタンス
	 */
	private static $instance = null;
	
	/**
	 * シングルトンインスタンスを取得
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * コンストラクタ
	 */
	private function __construct() {
	}
	
	/**
	 * プラグインを更新
	 *
	 * @param string $plugin_id プラグインID
	 * @param array|false $update_info 既に取得済みの更新情報（オプション）。指定された場合は再チェックをスキップ
	 * @return array|WP_Error 更新結果またはエラー
	 */
	public function update_plugin( $plugin_id, $update_info = false ) {
		$logger = GitHub_Push_Logger::get_instance();
		$github_api = GitHub_Push_Github_API::get_instance();
		
		// プラグイン情報を取得
		$plugin = $this->get_plugin_data( $plugin_id );
		
		if ( ! $plugin ) {
			$error = new WP_Error( 'plugin_not_found', __( 'プラグインが見つかりません', 'push-from-github' ) );
			$logger->log( $plugin_id, 'update', 'error', $error->get_error_message() );
			return $error;
		}
		
		// 更新チェック（既に取得済みの場合は再チェックをスキップ）
		if ( $update_info === false ) {
			$update_info = $github_api->check_for_updates( $plugin_id );
			
			if ( is_wp_error( $update_info ) ) {
				$logger->log( $plugin_id, 'update', 'error', $update_info->get_error_message() );
				return $update_info;
			}
		}

		$context = $this->resolve_component_context( $plugin );

		if ( is_wp_error( $context ) ) {
			$logger->log( $plugin_id, 'update', 'error', $context->get_error_message() );
			return $context;
		}
		
		if ( ! isset( $update_info['update_available'] ) || ! $update_info['update_available'] ) {
			$message = __( '更新はありません', 'push-from-github' );
			$logger->log( $plugin_id, 'update', 'info', $message );
			return array( 'message' => $message );
		}
		
		// バックアップを作成
		$backup_result = $this->create_backup( $plugin_id );
		
		if ( is_wp_error( $backup_result ) ) {
			$logger->log( $plugin_id, 'backup', 'error', $backup_result->get_error_message() );
			// バックアップエラーでも続行
		}
		
		// ZIPファイルをダウンロード
		$zip_path = $github_api->download_zip( $update_info['download_url'], isset( $plugin['token'] ) ? $plugin['token'] : '' );
		
		if ( is_wp_error( $zip_path ) ) {
			$logger->log( $plugin_id, 'update', 'error', $zip_path->get_error_message() );
			return $zip_path;
		}
		
		// ZIPファイルを展開
		$extracted_path = $this->extract_zip( $zip_path );
		
		if ( is_wp_error( $extracted_path ) ) {
			$logger->log( $plugin_id, 'update', 'error', $extracted_path->get_error_message() );
			wp_delete_file( $zip_path );
			return $extracted_path;
		}
		
		$component_dir = $context['dir'];
		$component_slug = $context['slug'];
		
		// 既存のプラグインが有効かどうかを確認
		if ( $context['is_theme'] ) {
			$is_active = in_array( $component_slug, array( get_stylesheet(), get_template() ), true );
		} else {
			$is_active = is_plugin_active( $component_slug );
		}
		
		// 既存のプラグインを削除
		if ( file_exists( $component_dir ) ) {
			$this->delete_directory( $component_dir );
		}
		
		// 展開したファイルを移動
		$move_result = $this->move_extracted_files( $extracted_path, $component_dir, $plugin );
		
		if ( is_wp_error( $move_result ) ) {
			$logger->log( $plugin_id, 'update', 'error', $move_result->get_error_message() );
			$this->cleanup_temp_files( $zip_path, $extracted_path );
			return $move_result;
		}
		
		// 一時ファイルを削除
		$this->cleanup_temp_files( $zip_path, $extracted_path );
		
		// プラグインを再読み込み
		wp_cache_flush();
		if ( $context['is_theme'] ) {
			wp_clean_themes_cache();
		}
		
		// 更新チェックのキャッシュをクリア
		$cache_key = 'github_push_update_' . $plugin_id;
		delete_transient( $cache_key );
		
		// プラグインが有効だった場合は再度有効化
		if ( $context['is_theme'] ) {
			if ( $is_active ) {
				switch_theme( $component_slug );
			}
		} elseif ( $is_active ) {
			activate_plugin( $component_slug );
		}
		
		// 通知を送信
		$notifications = GitHub_Push_Notifications::get_instance();
		$notifications->send_update_notification( $plugin_id, $update_info['latest_version'] );
		
		// translators: %s: Version number
		$message = sprintf(
			$context['is_theme'] ? __( 'テーマを %s に更新しました', 'push-from-github' ) : __( 'プラグインを %s に更新しました', 'push-from-github' ),
			$update_info['latest_version']
		);
		$logger->log( $plugin_id, 'update', 'success', $message, $update_info['latest_version'] );
		
		return array(
			'message' => $message,
			'version' => $update_info['latest_version'],
		);
	}
	
	/**
	 * ZIPファイルを展開
	 *
	 * @param string $zip_path ZIPファイルのパス
	 * @return string|WP_Error 展開先パスまたはエラー
	 */
	private function extract_zip( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_not_supported', __( 'ZIP拡張機能が利用できません', 'push-from-github' ) );
		}
		
		$zip = new ZipArchive();
		
		if ( $zip->open( $zip_path ) !== true ) {
			return new WP_Error( 'zip_open_failed', __( 'ZIPファイルを開けませんでした', 'push-from-github' ) );
		}
		
		$upload_dir = wp_upload_dir();
		$extract_dir = $upload_dir['basedir'] . '/github-push-temp/' . uniqid( 'extract-' );
		
		if ( ! wp_mkdir_p( $extract_dir ) ) {
			$zip->close();
			return new WP_Error( 'extract_dir_failed', __( '展開ディレクトリを作成できませんでした', 'push-from-github' ) );
		}
		
		$result = $zip->extractTo( $extract_dir );
		$zip->close();
		
		if ( ! $result ) {
			return new WP_Error( 'extract_failed', __( 'ZIPファイルの展開に失敗しました', 'push-from-github' ) );
		}
		
		return $extract_dir;
	}
	
	/**
	 * 展開したファイルを移動
	 *
	 * @param string $extracted_path 展開先パス
	 * @param string $plugin_dir プラグインディレクトリ
	 * @param array $plugin プラグイン情報
	 * @return bool|WP_Error 成功またはエラー
	 */
	private function move_extracted_files( $extracted_path, $plugin_dir, $plugin ) {
		// 展開されたディレクトリ内のファイルを探す
		$files = scandir( $extracted_path );
		
		if ( $files === false ) {
			return new WP_Error( 'scan_failed', __( '展開ディレクトリを読み取れませんでした', 'push-from-github' ) );
		}
		
		// 最初のディレクトリ（リポジトリ名-ブランチ名）を探す
		$source_dir = null;
		
		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' ) {
				continue;
			}
			
			$file_path = $extracted_path . '/' . $file;
			
			if ( is_dir( $file_path ) ) {
				$source_dir = $file_path;
				break;
			}
		}
		
		if ( ! $source_dir ) {
			return new WP_Error( 'source_dir_not_found', __( '展開されたディレクトリが見つかりませんでした', 'push-from-github' ) );
		}
		
		// プラグインディレクトリを作成
		if ( ! wp_mkdir_p( $plugin_dir ) ) {
			return new WP_Error( 'plugin_dir_failed', __( 'プラグインディレクトリを作成できませんでした', 'push-from-github' ) );
		}
		
		// ファイルを移動
		$result = $this->copy_directory( $source_dir, $plugin_dir );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		return true;
	}
	
	/**
	 * ディレクトリをコピー
	 *
	 * @param string $source ソースディレクトリ
	 * @param string $destination コピー先ディレクトリ
	 * @return bool|WP_Error 成功またはエラー
	 */
	private function copy_directory( $source, $destination ) {
		global $wp_filesystem;
		
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
		}
		
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'filesystem_not_available', __( 'ファイルシステムにアクセスできません', 'push-from-github' ) );
		}
		
		$result = copy_dir( $source, $destination );
		
		if ( ! $result ) {
			return new WP_Error( 'copy_failed', __( 'ファイルのコピーに失敗しました', 'push-from-github' ) );
		}
		
		return true;
	}
	
	/**
	 * ディレクトリを削除
	 *
	 * @param string $dir ディレクトリパス
	 * @return bool 成功
	 */
	private function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return true;
		}
		
		global $wp_filesystem;
		
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
		}
		
		if ( $wp_filesystem ) {
			return $wp_filesystem->rmdir( $dir, true );
		}
		
		// フォールバック
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$file_path = $dir . '/' . $file;
			
			if ( is_dir( $file_path ) ) {
				$this->delete_directory( $file_path );
			} else {
				wp_delete_file( $file_path );
			}
		}
		
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem->rmdir( $dir, true );
	}
	
	/**
	 * バックアップを作成
	 *
	 * @param string $plugin_id プラグインID
	 * @return array|WP_Error バックアップ情報またはエラー
	 */
	private function create_backup( $plugin_id ) {
		$plugin = $this->get_plugin_data( $plugin_id );
		
		if ( ! $plugin ) {
			return new WP_Error( 'plugin_not_found', __( 'プラグインが見つかりません', 'push-from-github' ) );
		}
		
		$context = $this->resolve_component_context( $plugin );

		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$component_dir = $context['dir'];

		if ( ! file_exists( $component_dir ) ) {
			$error_code = $context['is_theme'] ? 'theme_dir_not_found' : 'plugin_dir_not_found';
			$error_message = $context['is_theme']
				? __( 'テーマディレクトリが見つかりません', 'push-from-github' )
				: __( 'プラグインディレクトリが見つかりません', 'push-from-github' );
			return new WP_Error( $error_code, $error_message );
		}
		
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/github-push-backups';
		
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}
		
		$backup_name = $plugin_id . '-' . gmdate( 'Y-m-d-H-i-s' ) . '.zip';
		$backup_path = $backup_dir . '/' . $backup_name;
		
		// ZIPファイルを作成
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_not_supported', __( 'ZIP拡張機能が利用できません', 'push-from-github' ) );
		}
		
		$zip = new ZipArchive();
		
		if ( $zip->open( $backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return new WP_Error( 'backup_create_failed', __( 'バックアップファイルを作成できませんでした', 'push-from-github' ) );
		}
		
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $component_dir ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		
		// ディレクトリの正規化されたパスを取得（末尾のスラッシュを統一）
		$component_dir_normalized = rtrim( str_replace( '\\', '/', $component_dir ), '/' ) . '/';
		
		foreach ( $files as $file ) {
			if ( ! $file->isDir() ) {
				$file_path = $file->getRealPath();
				// 正規化されたパスを使用して相対パスを取得
				$file_path_normalized = str_replace( '\\', '/', $file_path );
				$relative_path = str_replace( $component_dir_normalized, '', $file_path_normalized );
				$zip->addFile( $file_path, $relative_path );
			}
		}
		
		$zip->close();
		
		// 現在のバージョンを取得
		$github_api = GitHub_Push_Github_API::get_instance();
		$current_version = $github_api->get_current_version( $plugin_id );
		
		// バージョンが '0.0.0' の場合は、プラグインファイルから直接取得を試みる
		if ( ! $context['is_theme'] && $current_version === '0.0.0' && ! empty( $plugin['plugin_slug'] ) ) {
			$plugin_file = WP_PLUGIN_DIR . '/' . $plugin['plugin_slug'];
			if ( file_exists( $plugin_file ) ) {
				$plugin_data = get_plugin_data( $plugin_file );
				if ( isset( $plugin_data['Version'] ) && ! empty( $plugin_data['Version'] ) ) {
					$current_version = $plugin_data['Version'];
				}
			}
		}
		
		// バックアップ情報を保存
		$backups = get_option( 'github_push_backups', array() );
		
		if ( ! isset( $backups[ $plugin_id ] ) ) {
			$backups[ $plugin_id ] = array();
		}
		
		$backups[ $plugin_id ][] = array(
			'path' => $backup_path,
			'version' => $current_version,
			'created_at' => current_time( 'mysql' ),
		);
		
		// バックアップ配列を created_at でソート（古い順）
		usort( $backups[ $plugin_id ], function( $a, $b ) {
			return strtotime( $a['created_at'] ) - strtotime( $b['created_at'] );
		} );
		
		// 古いバックアップを削除（最大5つまで保持）
		if ( count( $backups[ $plugin_id ] ) > 5 ) {
			$old_backups = array_slice( $backups[ $plugin_id ], 0, -5 );
			
			foreach ( $old_backups as $old_backup ) {
				if ( isset( $old_backup['path'] ) && file_exists( $old_backup['path'] ) ) {
					wp_delete_file( $old_backup['path'] );
				}
			}
			
			$backups[ $plugin_id ] = array_slice( $backups[ $plugin_id ], -5 );
		}
		
		update_option( 'github_push_backups', $backups );
		
		return array(
			'path' => $backup_path,
			'name' => $backup_name,
		);
	}
	
	/**
	 * 一時ファイルを削除
	 *
	 * @param string $zip_path ZIPファイルパス
	 * @param string $extracted_path 展開先パス
	 */
	private function cleanup_temp_files( $zip_path, $extracted_path ) {
		if ( file_exists( $zip_path ) ) {
			wp_delete_file( $zip_path );
		}
		
		if ( file_exists( $extracted_path ) ) {
			$this->delete_directory( $extracted_path );
		}
	}
	
	/**
	 * プラグインデータを取得
	 *
	 * @param string $plugin_id プラグインID
	 * @return array|false プラグイン情報またはfalse
	 */
	private function get_plugin_data( $plugin_id ) {
		$plugins = get_option( 'github_push_plugins', array() );
		
		if ( ! isset( $plugins[ $plugin_id ] ) ) {
			return false;
		}
		
		return $plugins[ $plugin_id ];
	}

	/**
	 * プラグイン/テーマのパス情報を取得
	 *
	 * @param array $plugin プラグイン情報
	 * @return array|WP_Error
	 */
	private function resolve_component_context( $plugin ) {
		$is_theme = isset( $plugin['type'] ) && 'theme' === $plugin['type'];
		
		if ( $is_theme ) {
			$slug = isset( $plugin['theme_slug'] ) ? $plugin['theme_slug'] : '';
			
			if ( empty( $slug ) ) {
				return new WP_Error( 'theme_slug_missing', __( 'テーマスラッグが指定されていません', 'push-from-github' ) );
			}
			
			$dir = trailingslashit( get_theme_root() ) . $slug;
		} else {
			$slug = isset( $plugin['plugin_slug'] ) ? $plugin['plugin_slug'] : '';
			
			if ( empty( $slug ) ) {
				return new WP_Error( 'plugin_slug_missing', __( 'プラグインスラッグが指定されていません', 'push-from-github' ) );
			}
			
			$dir_name = dirname( $slug );
			if ( '.' === $dir_name || '/' === $dir_name ) {
				$dir = WP_PLUGIN_DIR;
			} else {
				$dir = WP_PLUGIN_DIR . '/' . $dir_name;
			}
		}
		
		return array(
			'is_theme'   => $is_theme,
			'slug'       => $slug,
			'dir'        => untrailingslashit( $dir ),
			'type_label' => $is_theme ? __( 'テーマ', 'push-from-github' ) : __( 'プラグイン', 'push-from-github' ),
		);
	}
}

