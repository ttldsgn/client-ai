<?php
/**
 * Admin view: AI Chatbot Models management page.
 *
 * @package ClientAI
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap aicb-wrap">
	<h1><?php esc_html_e( 'Client AI — AI Models', 'ai-chatbot' ); ?></h1>
	<p><?php esc_html_e( 'Manage the AI models available for each provider. Built-in models are seeded from the plugin and cannot be deleted. Add custom models for any provider, or reset a provider to its default models.', 'ai-chatbot' ); ?></p>

	<hr />

	<?php if ( $edit_row ) : ?>
		<!-- Edit Model Form -->
		<div class="aicb-section">
			<h2><?php esc_html_e( 'Edit Model', 'ai-chatbot' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'aicb_models_action', 'aicb_models_nonce' ); ?>
				<input type="hidden" name="aicb_action" value="update" />
				<input type="hidden" name="model_db_id" value="<?php echo (int) $edit_row->id; ?>" />

				<table class="form-table">
					<tr>
						<th scope="row"><label for="provider_id"><?php esc_html_e( 'Provider ID', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="provider_id" name="provider_id" value="<?php echo esc_attr( $edit_row->provider_id ); ?>" class="regular-text" readonly />
							<p class="description"><?php esc_html_e( 'Provider identifier (cannot be changed after creation).', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="provider_name"><?php esc_html_e( 'Provider Display Name', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="provider_name" name="provider_name" value="<?php echo esc_attr( $edit_row->provider_name ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="model_id"><?php esc_html_e( 'Model ID', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="model_id" name="model_id" value="<?php echo esc_attr( $edit_row->model_id ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'The model identifier sent to the API (e.g., claude-sonnet-4-20250514).', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="name"><?php esc_html_e( 'Display Name', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="name" name="name" value="<?php echo esc_attr( $edit_row->name ); ?>" class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="description"><?php esc_html_e( 'Description', 'ai-chatbot' ); ?></label></th>
						<td>
							<textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $edit_row->description ?? '' ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="context_k"><?php esc_html_e( 'Context Window (K)', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="number" id="context_k" name="context_k" value="<?php echo (int) $edit_row->context_k; ?>" min="0" step="1" class="small-text" />
							<p class="description"><?php esc_html_e( 'Context window size in thousands of tokens (e.g., 128 for 128K).', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Flags', 'ai-chatbot' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="recommended" value="1" <?php checked( $edit_row->recommended, 1 ); ?> />
								<?php esc_html_e( 'Recommended', 'ai-chatbot' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="supports_tools" value="1" <?php checked( $edit_row->supports_tools, 1 ); ?> />
								<?php esc_html_e( 'Supports Tools (Function Calling)', 'ai-chatbot' ); ?>
							</label>
						</td>
					</tr>

					<!-- Model-Specific Base URL & API Key configuration row (Decrypted on-demand for Custom Models) -->
					<?php
					$show_custom_fields = ( $edit_row->provider_id === 'custom' ) ? '' : 'style="display:none;"';
					$has_custom_key     = ! empty( $edit_row->api_key ) ? 'XXXXXXXXXXXXXXXX' : '';
					?>
					<tr class="aicb-custom-provider-only" <?php echo $show_custom_fields; ?>>
						<th scope="row"><label for="api_endpoint"><?php esc_html_e( 'Base URL Endpoint', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="url" id="api_endpoint" name="api_endpoint" value="<?php echo esc_url( $edit_row->api_endpoint ?? '' ); ?>" class="regular-text" placeholder="https://openrouter.ai/api/v1/chat/completions" />
							<p class="description"><?php esc_html_e( 'The OpenAI-compatible Base URL for this custom model (e.g. https://openrouter.ai/api/v1/chat/completions). Leave blank to fall back to global Custom Endpoint.', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="aicb-custom-provider-only" <?php echo $show_custom_fields; ?>>
						<th scope="row"><label for="api_key"><?php esc_html_e( 'Custom API Key', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="api_key" name="api_key" value="<?php echo esc_attr( $has_custom_key ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom API key if required', 'ai-chatbot' ); ?>" />
							<p class="description"><?php esc_html_e( 'API key for this specific custom model. Encrypted securely in the database via AES-256-GCM. Leave blank to fall back to global Custom API Key.', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Update Model', 'ai-chatbot' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-chatbot-models' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'ai-chatbot' ); ?></a>
				</p>
			</form>
		</div>
	<?php else : ?>
		<!-- Add Model Form -->
		<div class="aicb-section">
			<h2><?php esc_html_e( 'Add Custom Model', 'ai-chatbot' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'aicb_models_action', 'aicb_models_nonce' ); ?>
				<input type="hidden" name="aicb_action" value="add" />

				<table class="form-table">
					<tr>
						<th scope="row"><label for="provider_id"><?php esc_html_e( 'Provider', 'ai-chatbot' ); ?></label></th>
						<td>
							<select id="provider_id" name="provider_id" class="regular-text">
								<option value=""><?php esc_html_e( '— Select Provider —', 'ai-chatbot' ); ?></option>
								<?php foreach ( $provider_options as $pid => $pname ) : ?>
									<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?> (<?php echo esc_html( $pid ); ?>)</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select which provider this model belongs to.', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="provider_name"><?php esc_html_e( 'Provider Display Name (optional)', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="provider_name" name="provider_name" value="" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Leave blank to use the provider name from the selection above.', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="model_id"><?php esc_html_e( 'Model ID *', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="model_id" name="model_id" value="" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'The model identifier sent to the API (e.g., claude-sonnet-4-20250514). Must be unique per provider.', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="name"><?php esc_html_e( 'Display Name *', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="text" id="name" name="name" value="" class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="description"><?php esc_html_e( 'Description', 'ai-chatbot' ); ?></label></th>
						<td>
							<textarea id="description" name="description" rows="3" class="large-text"></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="context_k"><?php esc_html_e( 'Context Window (K)', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="number" id="context_k" name="context_k" value="0" min="0" step="1" class="small-text" />
							<p class="description"><?php esc_html_e( 'Context window size in thousands of tokens (e.g., 128 for 128K).', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Flags', 'ai-chatbot' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="recommended" value="1" />
								<?php esc_html_e( 'Recommended', 'ai-chatbot' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="supports_tools" value="1" checked />
								<?php esc_html_e( 'Supports Tools (Function Calling)', 'ai-chatbot' ); ?>
							</label>
						</td>
					</tr>

					<!-- Model-Specific Base URL & API Key configuration row (Decrypted on-demand for Custom Models) -->
					<tr class="aicb-custom-provider-only" style="display:none;">
						<th scope="row"><label for="api_endpoint"><?php esc_html_e( 'Base URL Endpoint', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="url" id="api_endpoint" name="api_endpoint" value="" class="regular-text" placeholder="https://openrouter.ai/api/v1/chat/completions" />
							<p class="description"><?php esc_html_e( 'The OpenAI-compatible Base URL for this custom model (e.g. https://openrouter.ai/api/v1/chat/completions). Leave blank to fall back to global Custom Endpoint.', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
					<tr class="aicb-custom-provider-only" style="display:none;">
						<th scope="row"><label for="api_key"><?php esc_html_e( 'Custom API Key', 'ai-chatbot' ); ?></label></th>
						<td>
							<input type="password" id="api_key" name="api_key" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom API key if required', 'ai-chatbot' ); ?>" />
							<p class="description"><?php esc_html_e( 'API key for this specific custom model. Encrypted securely in the database via AES-256-GCM. Leave blank to fall back to global Custom API Key.', 'ai-chatbot' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Model', 'ai-chatbot' ); ?></button>
				</p>
			</form>
		</div>
	<?php endif; ?>

	<!-- Reset Provider to Defaults -->
	<div class="aicb-section">
		<h2><?php esc_html_e( 'Reset Provider to Defaults', 'ai-chatbot' ); ?></h2>
		<p><?php esc_html_e( 'This will remove all custom models for the selected provider and re-seed the default models from the plugin.', 'ai-chatbot' ); ?></p>
		<form method="post" style="display:inline-block;">
			<?php wp_nonce_field( 'aicb_models_action', 'aicb_models_nonce' ); ?>
			<input type="hidden" name="aicb_action" value="reset_provider" />
			<select name="reset_provider_id" required>
				<option value=""><?php esc_html_e( '— Select Provider —', 'ai-chatbot' ); ?></option>
				<?php foreach ( $provider_options as $pid => $pname ) : ?>
					<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure? This will delete all custom models for this provider.', 'ai-chatbot' ); ?>');"><?php esc_html_e( 'Reset Provider', 'ai-chatbot' ); ?></button>
		</form>
	</div>

	<!-- Models List -->
	<div class="aicb-section">
		<h2><?php esc_html_e( 'All Models', 'ai-chatbot' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Models are listed grouped by provider. Built-in models are marked with a badge.', 'ai-chatbot' ); ?></p>

		<?php if ( empty( $providers_models ) ) : ?>
			<p><?php esc_html_e( 'No models found. Activate the plugin or seed models from the Settings page.', 'ai-chatbot' ); ?></p>
		<?php else : ?>
			<?php foreach ( $providers_models as $pid => $pgroup ) : ?>
				<h3 style="margin-bottom:6px;margin-top:24px;">
					<?php echo esc_html( $pgroup['provider_name'] ); ?>
					<code style="font-size:12px;font-weight:normal;"><?php echo esc_html( $pid ); ?></code>
					<span class="aicb-tag aicb-tag-provider"><?php echo count( $pgroup['models'] ) . ' ' . esc_html__( 'models', 'ai-chatbot' ); ?></span>
				</h3>
				<table class="wp-list-table widefat fixed striped" style="margin-top:4px;">
					<thead>
						<tr>
							<th scope="col" style="width:30px;"><?php esc_html_e( '#', 'ai-chatbot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Model ID', 'ai-chatbot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'ai-chatbot' ); ?></th>
							<th scope="col" style="width:60px;"><?php esc_html_e( 'Context', 'ai-chatbot' ); ?></th>
							<th scope="col" style="width:80px;"><?php esc_html_e( 'Recommended', 'ai-chatbot' ); ?></th>
							<th scope="col" style="width:80px;"><?php esc_html_e( 'Tools', 'ai-chatbot' ); ?></th>
							<th scope="col" style="width:60px;"><?php esc_html_e( 'Status', 'ai-chatbot' ); ?></th>
							<th scope="col" style="width:60px;"><?php esc_html_e( 'Type', 'ai-chatbot' ); ?></th>
							<th scope="col" style="width:140px;"><?php esc_html_e( 'Actions', 'ai-chatbot' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $idx = 1; ?>
						<?php foreach ( $pgroup['models'] as $model ) : ?>
							<tr>
								<td><?php echo $idx++; ?></td>
								<td><code><?php echo esc_html( $model->model_id ); ?></code></td>
								<td>
									<strong><?php echo esc_html( $model->name ); ?></strong>
									<?php if ( ! empty( $model->description ) ) : ?>
										<br /><span class="description"><?php echo esc_html( $model->description ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo $model->context_k ? (int) $model->context_k . 'K' : '—'; ?></td>
								<td><?php echo $model->recommended ? '<span style="color:#166534;font-weight:600;">★ ' . esc_html__( 'Yes', 'ai-chatbot' ) . '</span>' : '—'; ?></td>
								<td><?php echo $model->supports_tools ? '<span style="color:#2563eb;">✓ ' . esc_html__( 'Yes', 'ai-chatbot' ) . '</span>' : '—'; ?></td>
								<td>
									<?php if ( $model->active ) : ?>
										<span style="color:#166534;font-weight:600;"><?php esc_html_e( 'Active', 'ai-chatbot' ); ?></span>
									<?php else : ?>
										<span style="color:#991b1b;"><?php esc_html_e( 'Inactive', 'ai-chatbot' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $model->is_custom ) : ?>
										<span class="aicb-tag" style="background:#fef3c7;color:#92400e;"><?php esc_html_e( 'Custom', 'ai-chatbot' ); ?></span>
									<?php else : ?>
										<span class="aicb-tag" style="background:#e0f2fe;color:#0369a1;"><?php esc_html_e( 'Built-in', 'ai-chatbot' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<div style="display:flex;gap:4px;flex-wrap:wrap;">
										<!-- Toggle Active/Inactive -->
										<form method="post" style="display:inline;">
											<?php wp_nonce_field( 'aicb_models_action', 'aicb_models_nonce' ); ?>
											<input type="hidden" name="aicb_action" value="toggle_active" />
											<input type="hidden" name="model_db_id" value="<?php echo (int) $model->id; ?>" />
											<button type="submit" class="button button-small">
												<?php echo $model->active ? esc_html__( 'Deactivate', 'ai-chatbot' ) : esc_html__( 'Activate', 'ai-chatbot' ); ?>
											</button>
										</form>

										<!-- Edit -->
										<a href="<?php echo esc_url( add_query_arg( 'edit_model', $model->id, admin_url( 'admin.php?page=ai-chatbot-models' ) ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'ai-chatbot' ); ?></a>

										<!-- Delete (custom only) -->
										<?php if ( $model->is_custom ) : ?>
											<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this custom model?', 'ai-chatbot' ); ?>');">
												<?php wp_nonce_field( 'aicb_models_action', 'aicb_models_nonce' ); ?>
												<input type="hidden" name="aicb_action" value="delete" />
												<input type="hidden" name="model_db_id" value="<?php echo (int) $model->id; ?>" />
												<button type="submit" class="button button-small" style="color:#991b1b;"><?php esc_html_e( 'Delete', 'ai-chatbot' ); ?></button>
											</form>
										<?php else : ?>
											<span class="description"><?php esc_html_e( 'Built-in', 'ai-chatbot' ); ?></span>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<hr />
	<p class="description">
		<?php esc_html_e( 'Note: Models are loaded from the database. Built-in models are seeded from', 'ai-chatbot' ); ?>
		<code>assets/models.json</code>
		<?php esc_html_e( 'on plugin activation. Editing the JSON file directly no longer has any effect after activation.', 'ai-chatbot' ); ?>
	</p>
</div>

<script>
jQuery(document).ready(function($) {
	function toggleCustomProviderFields() {
		var selectedProvider = $('#provider_id').val();
		if (selectedProvider === 'custom') {
			$('.aicb-custom-provider-only').slideDown(200);
		} else {
			$('.aicb-custom-provider-only').slideUp(200);
			// Reset input values when closing to avoid accidental submissions
			$('.aicb-custom-provider-only input').val('');
		}
	}
	$('#provider_id').on('change', toggleCustomProviderFields);
	if ($('#provider_id').length) {
		toggleCustomProviderFields();
	}
});
</script>
