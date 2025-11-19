<?php
/**
 * プラグイン編集フォーム
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// エラーメッセージの表示
$error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
$error_message = isset( $_GET['error_message'] ) ? urldecode( sanitize_text_field( $_GET['error_message'] ) ) : '';

if ( ! empty( $error ) ) {
	if ( ! empty( $error_message ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_message ) . '</p></div>';
	} else {
		// エラーコードに基づいてメッセージを表示
		$error_messages = array(
			'repo_not_found' => __( '指定されたGitHubリポジトリが見つかりませんでした。', 'github-push' ),
			'repo_access_error' => __( 'リポジトリにアクセスできませんでした。', 'github-push' ),
			'branch_access_error' => __( '指定されたブランチにアクセスできませんでした。', 'github-push' ),
			'plugin_file_not_found' => __( 'プラグインファイルが見つかりませんでした。リポジトリがWordPressプラグインではない可能性があります。', 'github-push' ),
			'plugin_file_read_error' => __( 'プラグインファイルの読み込みに失敗しました。', 'github-push' ),
			'plugin_file_content_error' => __( 'プラグインファイルの内容が取得できませんでした。', 'github-push' ),
			'plugin_file_decode_error' => __( 'プラグインファイルの内容をデコードできませんでした。', 'github-push' ),
			'invalid_plugin_header' => __( 'プラグインヘッダーが正しく記載されていません。このリポジトリはWordPressプラグインではない可能性があります。', 'github-push' ),
			'invalid_url' => __( '無効なGitHub URLです。', 'github-push' ),
		);
		$message = isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : __( 'エラーが発生しました', 'github-push' );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}

$is_edit = ! empty( $plugin_id ) && ! empty( $plugin );
$plugin_name = isset( $plugin['plugin_name'] ) ? $plugin['plugin_name'] : '';
$repo_url = isset( $plugin['repo_url'] ) ? $plugin['repo_url'] : '';
$branch = isset( $plugin['branch'] ) ? $plugin['branch'] : 'main';
$plugin_slug = isset( $plugin['plugin_slug'] ) ? $plugin['plugin_slug'] : '';
$token = isset( $plugin['token'] ) ? $plugin['token'] : '';
$use_tags = isset( $plugin['use_tags'] ) ? $plugin['use_tags'] : false;
?>

<div class="wrap">
	<h1><?php echo $is_edit ? esc_html__( 'プラグインを編集', 'github-push' ) : esc_html__( '新しいプラグインを追加', 'github-push' ); ?></h1>
	
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'github_push_save_plugin' ); ?>
		<input type="hidden" name="action" value="github_push_save_plugin">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="plugin_id" value="<?php echo esc_attr( $plugin_id ); ?>">
		<?php endif; ?>
		
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="repo_url"><?php echo esc_html__( 'GitHubリポジトリURL', 'github-push' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="url" id="repo_url" name="repo_url" value="<?php echo esc_url( $repo_url ); ?>" class="regular-text" required>
						<p class="description">
							<?php echo esc_html__( '管理したいGitHubリポジトリのURLを入力してください。', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( '例: https://github.com/owner/repo または git@github.com:owner/repo.git', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( '公開リポジトリと非公開リポジトリの両方に対応しています。非公開リポジトリの場合は、Personal Access Tokenの設定が必要です。', 'github-push' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="use_tags"><?php echo esc_html__( '更新方法', 'github-push' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="use_tags" name="use_tags" value="1" <?php checked( $use_tags ); ?>>
							<?php echo esc_html__( 'タグを使用する', 'github-push' ); ?>
						</label>
						<p class="description">
							<?php echo esc_html__( 'プラグインの更新をどのように取得するかを選択します。', 'github-push' ); ?>
							<br>
							<strong><?php echo esc_html__( 'タグを使用する場合:', 'github-push' ); ?></strong>
							<?php echo esc_html__( 'GitHubのリリースタグ（例: v1.0.0, v1.2.3）から最新バージョンを取得します。セマンティックバージョニングに適しており、安定版のリリースを管理する場合に推奨されます。', 'github-push' ); ?>
							<br>
							<strong><?php echo esc_html__( 'ブランチを使用する場合:', 'github-push' ); ?></strong>
							<?php echo esc_html__( '指定したブランチ（例: main, develop）の最新コミットから更新を取得します。継続的な開発や最新の変更を常に反映したい場合に適しています。', 'github-push' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="branch"><?php echo esc_html__( 'ブランチ名', 'github-push' ); ?></label>
					</th>
					<td>
						<input type="text" id="branch" name="branch" value="<?php echo esc_attr( $branch ); ?>" class="regular-text">
						<p class="description">
							<?php echo esc_html__( '更新を取得するGitHubブランチ名を指定してください。', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( '一般的なブランチ名: main, master, develop, staging など', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( 'デフォルト: main（未入力の場合）', 'github-push' ); ?>
							<br>
							<strong><?php echo esc_html__( '注意:', 'github-push' ); ?></strong>
							<?php echo esc_html__( '「タグを使用する」にチェックが入っている場合は、この設定は無視され、タグから最新バージョンが取得されます。', 'github-push' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="plugin_slug"><?php echo esc_html__( 'プラグインスラッグ', 'github-push' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<select id="plugin_slug_select" class="regular-text" style="margin-bottom: 10px;">
							<option value=""><?php echo esc_html__( 'プラグインを選択してください', 'github-push' ); ?></option>
							<?php foreach ( $installed_plugins as $slug => $plugin_data ) : ?>
								<?php if ( ! in_array( $slug, $registered_slugs, true ) ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $plugin_slug, $slug ); ?>>
										<?php echo esc_html( $plugin_data['Name'] ); ?> (<?php echo esc_html( $slug ); ?>)
									</option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
						<input type="text" id="plugin_slug" name="plugin_slug" value="<?php echo esc_attr( $plugin_slug ); ?>" class="regular-text" required>
						<p class="description">
							<?php echo esc_html__( 'このGitHubリポジトリと連携するWordPressプラグインを指定してください。', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( '上記のドロップダウンから既にインストールされているプラグインを選択するか、手動でプラグインスラッグを入力してください。', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( 'プラグインスラッグの形式: フォルダ名/メインファイル名.php', 'github-push' ); ?>
							<br>
							<?php echo esc_html__( '例: my-plugin/my-plugin.php, woocommerce/woocommerce.php', 'github-push' ); ?>
							<br>
							<strong><?php echo esc_html__( '注意:', 'github-push' ); ?></strong>
							<?php echo esc_html__( '既に他のGitHubリポジトリと連携されているプラグインは選択できません。', 'github-push' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="token"><?php echo esc_html__( 'Personal Access Token', 'github-push' ); ?></label>
					</th>
					<td>
						<input type="password" id="token" name="token" value="<?php echo esc_attr( $token ); ?>" class="regular-text">
						<p class="description">
							<?php echo esc_html__( 'GitHub APIにアクセスするための認証トークンです。', 'github-push' ); ?>
							<br>
							<strong><?php echo esc_html__( '必要な場合:', 'github-push' ); ?></strong>
							<?php echo esc_html__( '非公開リポジトリにアクセスする場合は必須です。公開リポジトリでも使用可能で、APIレート制限が緩和されます（認証なし: 60リクエスト/時 → 認証あり: 5,000リクエスト/時）。', 'github-push' ); ?>
							<br><br>
							<strong><?php echo esc_html__( 'トークンの作成方法:', 'github-push' ); ?></strong>
							<?php echo esc_html__( 'GitHubの', 'github-push' ); ?>
							<a href="https://github.com/settings/tokens" target="_blank"><?php echo esc_html__( 'Settings > Developer settings > Personal access tokens', 'github-push' ); ?></a>
							<?php echo esc_html__( 'から「Generate new token (classic)」をクリックし、必要なスコープ（', 'github-push' ); ?><code>repo</code><?php echo esc_html__( ' または ', 'github-push' ); ?><code>public_repo</code><?php echo esc_html__( '）を選択してトークンを生成してください。', 'github-push' ); ?>
							<br><br>
							<strong><?php echo esc_html__( '注意:', 'github-push' ); ?></strong>
							<?php echo esc_html__( 'トークンは機密情報です。他人に共有せず、漏洩した場合はすぐにGitHubで無効化してください。', 'github-push' ); ?>
							<br>
							<a href="https://github.com/settings/tokens" target="_blank" class="button button-secondary" style="margin-top: 5px;">
								<?php echo esc_html__( 'GitHubでトークンを生成', 'github-push' ); ?>
							</a>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<div class="notice notice-warning inline" style="margin: 15px 0 10px 0; padding: 10px;">
			<p style="margin: 0;">
				<strong><?php echo esc_html__( '重要: バージョン情報の取得について', 'github-push' ); ?></strong>
				<br>
				<?php echo esc_html__( 'GitHubリポジトリにアップロードされているプラグインファイルには、WordPressプラグインの標準ヘッダー形式（Plugin Name、Version など）が正しく記載されている必要があります。', 'github-push' ); ?>
				<br>
				<?php echo esc_html__( 'ヘッダー形式が正しくない場合、バージョン情報を取得できず、バージョンが上がっても更新情報が表示されません。', 'github-push' ); ?>
				<br>
				<?php echo esc_html__( 'プラグインファイルの先頭に以下のような形式でヘッダーが記載されているか確認してください:', 'github-push' ); ?>
				<br>
				<code style="display: block; margin-top: 5px; padding: 5px; background: #f0f0f0;">
					<?php echo esc_html__( '/**', 'github-push' ); ?><br>
					<?php echo esc_html__( 'Plugin Name: プラグイン名', 'github-push' ); ?><br>
					<?php echo esc_html__( 'Version: 1.0.0', 'github-push' ); ?><br>
					<?php echo esc_html__( ' */', 'github-push' ); ?>
				</code>
			</p>
		</div>
		
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__( '保存', 'github-push' ); ?>">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'github-push' ), admin_url( 'admin.php' ) ) ); ?>" class="button">
				<?php echo esc_html__( 'キャンセル', 'github-push' ); ?>
			</a>
		</p>
	</form>
</div>

