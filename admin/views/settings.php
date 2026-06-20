<?php defined( 'ABSPATH' ) || exit; ?>
<style>
/* Dynamic Tab Navigation Styling */
.aicb-tabs-nav {
    display: flex;
    margin-bottom: 0; /* Connected directly to the section below */
    gap: 4px;
    flex-wrap: wrap;
    position: relative;
    z-index: 2; /* Sits above the section borders */
}
.aicb-tab-link {
    padding: 10px 18px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 6px 6px 0 0;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.15s ease;
    margin-bottom: -1px; /* Overlaps section top border to mask the gap */
    position: relative;
}
.aicb-tab-link:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.aicb-tab-link.active {
    background: #fff;
    color: #2563eb;
    border-color: #cbd5e1;
    border-bottom-color: #fff; /* Masks top border of section below */
    border-top: 2px solid #2563eb;
    padding-top: 9px;
    z-index: 3;
}
.aicb-tab-panel {
    display: none;
    position: relative;
    z-index: 1;
}
.aicb-tab-panel.active {
    display: block;
}

/* 
 * Failsafe Progressive Enhancement:
 * Panels and external panels remain standard blocks by default. Hiding is only activated 
 * if JavaScript initializes correctly to prevent formatting locks.
 */
.aicb-js-active .aicb-tab-panel {
    display: none;
}
.aicb-js-active .aicb-tab-panel.active {
    display: block;
}
.aicb-js-active #tab-advanced-migration-external {
    display: none;
}

/* Flat top integration for the first section touching the bottom of the tabs */
.aicb-tab-panel > .aicb-section:first-of-type {
    border-top-left-radius: 0 !important;
    border-top-right-radius: 0 !important;
    border-color: #cbd5e1 !important;
    margin-top: 0 !important;
}

/* Clear margins from top toggle element inside Tab 5 */
.aicb-js-active #tab-advanced-migration > div:first-child {
    margin-top: 0 !important;
    padding-top: 15px;
}

/* Force standard WordPress form-table alignments for active key rows */
.form-table tr.aicb-key-row.active {
    display: table-row !important;
}
.form-table tr.aicb-key-row.active th {
    display: table-cell !important;
    vertical-align: top;
    text-align: left;
    padding: 20px 10px 20px 0;
    width: 200px;
}
.form-table tr.aicb-key-row.active td {
    display: table-cell !important;
    padding: 15px 10px;
}

.aicb-settings-submit-wrapper {
    margin-top: 20px;
}
</style>

<div class="wrap aicb-wrap">
    <h1><?php esc_html_e( 'Client AI — Settings', 'ai-chatbot' ); ?></h1>
    
    <?php settings_errors( 'aicb_options' ); ?>

    <!-- Horizontal Tab Navigation Menu -->
    <div class="aicb-tabs-nav">
        <button type="button" class="aicb-tab-link active" data-tab="tab-ai-engine"><?php esc_html_e( 'AI Engine & Language', 'ai-chatbot' ); ?></button>
        <button type="button" class="aicb-tab-link" data-tab="tab-knowledge-persona"><?php esc_html_e( 'Knowledge & Persona', 'ai-chatbot' ); ?></button>
        <button type="button" class="aicb-tab-link" data-tab="tab-widget-design"><?php esc_html_e( 'Chat Widget Design', 'ai-chatbot' ); ?></button>
        <button type="button" class="aicb-tab-link" data-tab="tab-behavior-escalation"><?php esc_html_e( 'Behavior & Escalation', 'ai-chatbot' ); ?></button>
        <button type="button" class="aicb-tab-link" data-tab="tab-advanced-migration"><?php esc_html_e( 'Advanced & Migration', 'ai-chatbot' ); ?></button>
    </div>

    <form method="post" action="options.php" style="margin: 0; padding: 0;">
        <?php settings_fields( 'aicb_options' ); ?>

        <!-- ── TAB 1: AI ENGINE & LANGUAGE ── -->
        <div id="tab-ai-engine" class="aicb-tab-panel active">
            <!-- ── PROVIDER & MODEL ── -->
            <div class="aicb-section">
                <h2><?php esc_html_e( 'AI Provider & Model', 'ai-chatbot' ); ?></h2>
                <p style="color:#555;margin-top:-8px"><?php esc_html_e( 'Select your provider, then choose a model. Each provider uses its own API key.', 'ai-chatbot' ); ?></p>

                <div class="aicb-provider-grid" id="aicb-provider-grid">
                    <?php foreach ( $providers as $pid => $pdata ) :
                        $sel = ( $pid === $cur_provider ) ? ' selected' : '';
                        ?>
                        <label class="aicb-provider-card<?= $sel ?>" data-provider="<?= esc_attr( $pid ) ?>">
                            <input type="radio" name="aicb_provider" value="<?= esc_attr( $pid ) ?>"<?= checked( $cur_provider, $pid, false ) ?>>
                            <span class="aicb-provider-logo"><?= $logos[ $pid ] ?? '🤖' ?></span>
                            <span class="aicb-provider-name"><?= esc_html( $pdata['name'] ) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <table class="form-table" style="margin-top:0">
                    <?php foreach ( $providers as $pid => $pdata ) :
                        // Skip the custom key-row entirely since keys and endpoints are now managed in AI Models page
                        if ( $pid === 'custom' ) {
                            continue;
                        }
                        $active    = ( $pid === $cur_provider ) ? ' active' : '';

                        $has_const = defined( 'AICB_KEY_' . strtoupper( $pid ) );
                        $disabled  = $has_const ? ' disabled' : '';

                        if ( $has_const ) {
                            $display_val = __( 'Defined securely inside wp-config.php', 'ai-chatbot' );
                        } else {
                            $stored_key  = aicb_get_key( $pid );
                            $display_val = ! empty( $stored_key ) ? 'XXXXXXXXXXXXXXXX' : '';
                        }

                        $placeholder = $has_const ? __( 'Configured by wp-config constants', 'ai-chatbot' ) : 'sk-…';
                        ?>
                        <tr class="aicb-key-row<?= $active ?>" id="aicb-keyrow-<?= esc_attr( $pid ) ?>">
                            <th style="width:200px">
                                <label for="aicb_key_<?= esc_attr( $pid ) ?>"><?= esc_html( $pdata['key_label'] ) ?></label>
                            </th>
                            <td>
                                <input type="password" id="aicb_key_<?= esc_attr( $pid ) ?>"
                                name="aicb_key_<?= esc_attr( $pid ) ?>"
                                value="<?= esc_attr( $display_val ) ?>"
                                class="regular-text" autocomplete="new-password"
                                placeholder="<?= esc_attr( $placeholder ) ?>"<?= $disabled ?> />
                                <?php if ( $has_const ) : ?>
                                    <p class="description" style="color: #16a34a; font-weight: 600;"><?php esc_html_e( '✓ This key is defined as a PHP constant and cannot be modified here.', 'ai-chatbot' ); ?></p>
                                <?php elseif ( ! empty( $pdata['key_help'] ) ) : ?>
                                    <p class="description"><?= esc_html( $pdata['key_help'] ) ?>
                                    <?php if ( ! empty( $pdata['docs_url'] ) ) : ?>
                                        — <a href="<?= esc_url( $pdata['docs_url'] ) ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Docs ↗', 'ai-chatbot' ); ?></a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <th><label for="aicb_model"><?php esc_html_e( 'Model', 'ai-chatbot' ); ?></label></th>
                    <td id="aicb-model-wrap">
                        <select id="aicb_model" name="aicb_model">
                            <?php
                            $models = aicb_get_models( $cur_provider );
                            foreach ( $models as $m ) {
                                $label = $m['name'] . ( $m['recommended'] ? ' ★' : '' );
                                printf( '<option value="%s"%s>%s</option>',
                                    esc_attr( $m['id'] ),
                                    selected( $cur_model, $m['id'], false ),
                                    esc_html( $label )
                                );
                            }
                            ?>
                        </select>
                        <p class="aicb-model-desc" id="aicb-model-desc"></p>
                    </td>
                </tr>
                </table>
            </div>

            <!-- ── LANGUAGE & LOCALIZATION ── -->
            <div class="aicb-section">
                <h2><?php esc_html_e( 'Language & Localization', 'ai-chatbot' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="aicb_chatbot_language_mode"><?php esc_html_e( 'Chatbot Language', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <select id="aicb_chatbot_language_mode" name="aicb_chatbot_language_mode">
                                <option value="auto" <?= selected( aicb_opt('chatbot_language_mode'), 'auto', false ) ?>><?php esc_html_e( 'Auto-detect (visitor browser language)', 'ai-chatbot' ); ?></option>
                                <option value="fixed" <?= selected( aicb_opt('chatbot_language_mode'), 'fixed', false ) ?>><?php esc_html_e( 'Fixed language (choose below)', 'ai-chatbot' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( "Auto-detect uses the visitor's browser language setting. Fixed forces all responses to a single language.", 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr id="aicb-fixed-language-row" style="<?= aicb_opt('chatbot_language_mode') === 'fixed' ? '' : 'display:none' ?>">
                        <th><label for="aicb_chatbot_language"><?php esc_html_e( 'Language', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <select id="aicb_chatbot_language" name="aicb_chatbot_language">
                                <option value="">— <?php esc_html_e( 'Select a language', 'ai-chatbot' ); ?> —</option>
                                <option value="English" <?= selected( aicb_opt('chatbot_language'), 'English', false ) ?>><?php esc_html_e( 'English', 'ai-chatbot' ); ?></option>
                                <option value="Español" <?= selected( aicb_opt('chatbot_language'), 'Español', false ) ?>><?php esc_html_e( 'Español', 'ai-chatbot' ); ?></option>
                                <option value="Français" <?= selected( aicb_opt('chatbot_language'), 'Français', false ) ?>><?php esc_html_e( 'Français', 'ai-chatbot' ); ?></option>
                                <option value="Deutsch" <?= selected( aicb_opt('chatbot_language'), 'Deutsch', false ) ?>><?php esc_html_e( 'Deutsch', 'ai-chatbot' ); ?></option>
                                <option value="Português" <?= selected( aicb_opt('chatbot_language'), 'Português', false ) ?>><?php esc_html_e( 'Português', 'ai-chatbot' ); ?></option>
                                <option value="Italiano" <?= selected( aicb_opt('chatbot_language'), 'Italiano', false ) ?>><?php esc_html_e( 'Italiano', 'ai-chatbot' ); ?></option>
                                <option value="Nederlands" <?= selected( aicb_opt('chatbot_language'), 'Nederlands', false ) ?>><?php esc_html_e( 'Nederlands', 'ai-chatbot' ); ?></option>
                                <option value="日本語" <?= selected( aicb_opt('chatbot_language'), '日本語', false ) ?>><?php esc_html_e( '日本語', 'ai-chatbot' ); ?></option>
                                <option value="中文 (简体)" <?= selected( aicb_opt('chatbot_language'), '中文 (简体)', false ) ?>><?php esc_html_e( '中文 (简体)', 'ai-chatbot' ); ?></option>
                                <option value="中文 (繁體)" <?= selected( aicb_opt('chatbot_language'), '中文 (繁體)', false ) ?>><?php esc_html_e( '中文 (繁體)', 'ai-chatbot' ); ?></option>
                                <option value="한국어" <?= selected( aicb_opt('chatbot_language'), '한국어', false ) ?>><?php esc_html_e( '한국어', 'ai-chatbot' ); ?></option>
                                <option value="Русский" <?= selected( aicb_opt('chatbot_language'), 'Русский', false ) ?>><?php esc_html_e( 'Русский', 'ai-chatbot' ); ?></option>
                                <option value="العربية" <?= selected( aicb_opt('chatbot_language'), 'العربية', false ) ?>><?php esc_html_e( 'العربية', 'ai-chatbot' ); ?></option>
                                <option value="हिन्दी" <?= selected( aicb_opt('chatbot_language'), 'हिन्दी', false ) ?>><?php esc_html_e( 'हिन्दी', 'ai-chatbot' ); ?></option>
                                <option value="Bahasa Indonesia" <?= selected( aicb_opt('chatbot_language'), 'Bahasa Indonesia', false ) ?>><?php esc_html_e( 'Bahasa Indonesia', 'ai-chatbot' ); ?></option>
                                <option value="Türkçe" <?= selected( aicb_opt('chatbot_language'), 'Türkçe', false ) ?>><?php esc_html_e( 'Türkçe', 'ai-chatbot' ); ?></option>
                                <option value="Polski" <?= selected( aicb_opt('chatbot_language'), 'Polski', false ) ?>><?php esc_html_e( 'Polski', 'ai-chatbot' ); ?></option>
                                <option value="Svenska" <?= selected( aicb_opt('chatbot_language'), 'Svenska', false ) ?>><?php esc_html_e( 'Svenska', 'ai-chatbot' ); ?></option>
                                <option value="Dansk" <?= selected( aicb_opt('chatbot_language'), 'Dansk', false ) ?>><?php esc_html_e( 'Dansk', 'ai-chatbot' ); ?></option>
                                <option value="Suomi" <?= selected( aicb_opt('chatbot_language'), 'Suomi', false ) ?>><?php esc_html_e( 'Suomi', 'ai-chatbot' ); ?></option>
                                <option value="Norsk" <?= selected( aicb_opt('chatbot_language'), 'Norsk', false ) ?>><?php esc_html_e( 'Norsk', 'ai-chatbot' ); ?></option>
                                <option value="Čeština" <?= selected( aicb_opt('chatbot_language'), 'Čeština', false ) ?>><?php esc_html_e( 'Čeština', 'ai-chatbot' ); ?></option>
                                <option value="Română" <?= selected( aicb_opt('chatbot_language'), 'Română', false ) ?>><?php esc_html_e( 'Română', 'ai-chatbot' ); ?></option>
                                <option value="Magyar" <?= selected( aicb_opt('chatbot_language'), 'Magyar', false ) ?>><?php esc_html_e( 'Magyar', 'ai-chatbot' ); ?></option>
                                <option value="Ελληνικά" <?= selected( aicb_opt('chatbot_language'), 'Ελληνικά', false ) ?>><?php esc_html_e( 'Ελληνικά', 'ai-chatbot' ); ?></option>
                                <option value="Tiếng Việt" <?= selected( aicb_opt('chatbot_language'), 'Tiếng Việt', false ) ?>><?php esc_html_e( 'Tiếng Việt', 'ai-chatbot' ); ?></option>
                                <option value="ไทย" <?= selected( aicb_opt('chatbot_language'), 'ไทย', false ) ?>><?php esc_html_e( 'ไทย', 'ai-chatbot' ); ?></option>
                                <option value="עברית" <?= selected( aicb_opt('chatbot_language'), 'עברית', false ) ?>><?php esc_html_e( 'עברית', 'ai-chatbot' ); ?></option>
                            </select>
                            <p class="description">Use a language name the AI can understand. Language is sent to the model as an instruction in the system prompt.</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── TAB 2: KNOWLEDGE & PERSONA ── -->
        <div id="tab-knowledge-persona" class="aicb-tab-panel">
            <!-- ── KNOWLEDGE BASE & CACHING ── -->
            <div class="aicb-section">
                <h2>Knowledge Base & Caching</h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Enable Caching', 'ai-chatbot' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aicb_enable_cache" value="1" <?= checked( aicb_opt('enable_cache'), 1 ) ?> />
                                <?php esc_html_e( 'Cache condensed summaries of pages to reduce API token usage (Recommended)', 'ai-chatbot' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_cache_duration"><?php esc_html_e( 'Cache Duration', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <select id="aicb_cache_duration" name="aicb_cache_duration">
                                <option value="0"    <?= selected( aicb_opt('cache_duration'), 0,    false ) ?>><?php esc_html_e( 'Until Content Changes (Infinite)', 'ai-chatbot' ); ?></option>
                                <option value="720"  <?= selected( aicb_opt('cache_duration'), 720,  false ) ?>><?php esc_html_e( '30 Days', 'ai-chatbot' ); ?></option>
                                <option value="168"  <?= selected( aicb_opt('cache_duration'), 168,  false ) ?>><?php esc_html_e( '7 Days', 'ai-chatbot' ); ?></option>
                                <option value="24"   <?= selected( aicb_opt('cache_duration'), 24,   false ) ?>><?php esc_html_e( '24 Hours', 'ai-chatbot' ); ?></option>
                                <option value="1"    <?= selected( aicb_opt('cache_duration'), 1,    false ) ?>><?php esc_html_e( '1 Hour', 'ai-chatbot' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Indexing Mode', 'ai-chatbot' ); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px">
                                <input type="radio" name="aicb_indexing_mode" value="opt-out" <?= checked( aicb_opt('indexing_mode'), 'opt-out' ) ?> />
                                <?php printf( __( '<strong>Include all pages (Opt-Out)</strong> — Pages are indexed unless manually excluded in the editor.', 'ai-chatbot' ) ); ?>
                            </label>
                            <label style="display:block">
                                <input type="radio" name="aicb_indexing_mode" value="opt-in" <?= checked( aicb_opt('indexing_mode'), 'opt-in' ) ?> />
                                <?php printf( __( '<strong>Only selected pages (Opt-In)</strong> — Pages are ignored unless manually included in the editor.', 'ai-chatbot' ) ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Allowed Post Types', 'ai-chatbot' ); ?></th>
                        <td>
                            <?php 
                            $post_types = get_post_types( [ 'public' => true ], 'objects' );
                            $selected_types = (array) aicb_opt( 'indexed_post_types' );
                            foreach ( $post_types as $type ) : 
                                if ( in_array( $type->name, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) continue;
                                $checked = in_array( $type->name, $selected_types, true ) ? 'checked' : '';
                            ?>
                                <label style="display:inline-block;margin-right:16px">
                                    <input type="checkbox" name="aicb_indexed_post_types[]" value="<?= esc_attr($type->name) ?>" <?= $checked ?> />
                                    <?= esc_html($type->label) ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                
                <div style="border-top:1px solid #eee;margin-top:20px;padding-top:20px">
                    <p class="description"><?php esc_html_e( 'If you updated your global system prompt or changed model providers, we recommend flushing the cache to force a regeneration of factual summaries.', 'ai-chatbot' ); ?></p>
                    <button type="submit" form="aicb-flush-cache-form" class="button button-secondary"><?php esc_html_e( 'Flush All Cached Summaries', 'ai-chatbot' ); ?></button>
                </div>
            </div>

            <!-- ── AI PERSONA & IDENTITY ── -->
            <div class="aicb-section">
                <h2><?php esc_html_e( 'AI Persona & Identity', 'ai-chatbot' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="aicb_business_name"><?php esc_html_e( 'Entity / Business Name', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="text" id="aicb_business_name" name="aicb_business_name" 
                                   value="<?= esc_attr( aicb_opt('business_name') ) ?>" class="regular-text" 
                                   placeholder="<?php esc_attr_e( 'e.g. Human Made', 'ai-chatbot' ); ?>" />
                            <p class="description"><?php esc_html_e( "Explicitly anchors the AI's identity so it never hallucinates your brand name from website taglines.", 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_pronoun_perspective"><?php esc_html_e( 'Pronouns / Perspective', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <select id="aicb_pronoun_perspective" name="aicb_pronoun_perspective">
                                <option value="first-plural"   <?= selected( aicb_opt('pronoun_perspective'), 'first-plural',   false ) ?>><?php esc_html_e( 'First-Person Plural (We / Our / Us) — For Teams & Agencies', 'ai-chatbot' ); ?></option>
                                <option value="first-singular" <?= selected( aicb_opt('pronoun_perspective'), 'first-singular', false ) ?>><?php esc_html_e( 'First-Person Singular (I / My / Me) — For Solo Freelancers', 'ai-chatbot' ); ?></option>
                                <option value="neutral"        <?= selected( aicb_opt('pronoun_perspective'), 'neutral',        false ) ?>><?php esc_html_e( 'Neutral Third-Person (The Company / The Service)', 'ai-chatbot' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_chatbot_tone"><?php esc_html_e( 'Tone Presets', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <select id="aicb_chatbot_tone" name="aicb_chatbot_tone">
                                <option value="casual"       <?= selected( aicb_opt('chatbot_tone'), 'casual',       false ) ?>><?php esc_html_e( 'Casual (Warm, approachable, and conversational)', 'ai-chatbot' ); ?></option>
                                <option value="professional" <?= selected( aicb_opt('chatbot_tone'), 'professional', false ) ?>><?php esc_html_e( 'Professional (Polite, direct, and authoritative)', 'ai-chatbot' ); ?></option>
                                <option value="minimalist"   <?= selected( aicb_opt('chatbot_tone'), 'minimalist',   false ) ?>><?php esc_html_e( 'Minimalist (Extremely brief, factual, and objective)', 'ai-chatbot' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── TAB 3: CHAT WIDGET DESIGN ── -->
        <div id="tab-widget-design" class="aicb-tab-panel">
            <!-- ── DISPLAY ── -->
            <div class="aicb-section">
                <h2><?php esc_html_e( 'Display Options', 'ai-chatbot' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Global Toggle', 'ai-chatbot' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="aicb_enabled" value="1" <?= checked( aicb_opt('enabled'), 1 ) ?> />
                            <?php esc_html_e( 'Enable the chatbot', 'ai-chatbot' ); ?></label>
                            <p class="description"><?php esc_html_e( 'When disabled, the chatbot is hidden everywhere — including pages using the [ai_chatbot] shortcode.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Auto-inject', 'ai-chatbot' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="aicb_show_on_all" value="1" <?= checked( aicb_opt('show_on_all'), 1 ) ?> />
                            <?php esc_html_e( 'Add the chatbot to all pages automatically', 'ai-chatbot' ); ?></label>
                            <p class="description"><?php esc_html_e( 'When unchecked, use [ai_chatbot] on specific pages only. Requires "Global Toggle" above to be enabled.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_position"><?php esc_html_e( 'Position', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <select id="aicb_position" name="aicb_position">
                                <option value="right"     <?= selected( aicb_opt('position'), 'right',     false ) ?>><?php esc_html_e( 'Bottom Right — floating button', 'ai-chatbot' ); ?></option>
                                <option value="left"      <?= selected( aicb_opt('position'), 'left',      false ) ?>><?php esc_html_e( 'Bottom Left — floating button', 'ai-chatbot' ); ?></option>
                                <option value="tab-right" <?= selected( aicb_opt('position'), 'tab-right', false ) ?>><?php esc_html_e( 'Vertical Tab — Right edge', 'ai-chatbot' ); ?></option>
                                <option value="tab-left"  <?= selected( aicb_opt('position'), 'tab-left',  false ) ?>><?php esc_html_e( 'Vertical Tab — Left edge', 'ai-chatbot' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_primary_color"><?php esc_html_e( 'Primary Color', 'ai-chatbot' ); ?></label></th>
                        <td><input type="color" id="aicb_primary_color" name="aicb_primary_color" value="<?= esc_attr( aicb_opt('primary_color') ) ?>" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Button Icon', 'ai-chatbot' ); ?></th>
                        <td>
                            <?php foreach ( [ 'chat' => '💬', 'bot' => '🤖', 'help' => '❓', 'star' => '⭐' ] as $k => $e ) : ?>
                                <label style="margin-right:14px;cursor:pointer">
                                    <input type="radio" name="aicb_icon" value="<?= esc_attr($k) ?>" <?= checked( aicb_opt('icon'), $k ) ?> style="display:none">
                                    <span style="font-size:26px" title="<?= esc_attr($k) ?>"><?= $e ?></span>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_chat_title"><?php esc_html_e( 'Chat Title', 'ai-chatbot' ); ?></label></th>
                        <td><input type="text" id="aicb_chat_title" name="aicb_chat_title" value="<?= esc_attr( aicb_opt('chat_title') ) ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="aicb_welcome_msg"><?php esc_html_e( 'Welcome Message', 'ai-chatbot' ); ?></label></th>
                        <td><input type="text" id="aicb_welcome_msg" name="aicb_welcome_msg" value="<?= esc_attr( aicb_opt('welcome_msg') ) ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="aicb_placeholder"><?php esc_html_e( 'Input Placeholder', 'ai-chatbot' ); ?></label></th>
                        <td><input type="text" id="aicb_placeholder" name="aicb_placeholder" value="<?= esc_attr( aicb_opt('placeholder') ) ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="aicb_footer_text"><?php esc_html_e( 'Chat Footer Text', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="text" id="aicb_footer_text" name="aicb_footer_text" 
                                   value="<?= esc_attr( aicb_opt('footer_text') ) ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Leave blank to hide the footer text.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── TAB 4: BEHAVIOR & ESCALATION ── -->
        <div id="tab-behavior-escalation" class="aicb-tab-panel">
            <!-- ── BEHAVIOUR ── -->
            <div class="aicb-section">
                <h2><?php esc_html_e( 'Behaviour', 'ai-chatbot' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Enable Feedback', 'ai-chatbot' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aicb_enable_feedback" value="1" <?= checked( aicb_opt('enable_feedback'), 1 ) ?> />
                                <?php esc_html_e( 'Show thumbs up/down after each response to collect visitor feedback', 'ai-chatbot' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Adds a feedback column to the logs table. Dashboard shows satisfaction rate when enabled.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_max_tokens"><?php esc_html_e( 'Max Response Tokens', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="number" id="aicb_max_tokens" name="aicb_max_tokens"
                            value="<?= esc_attr( aicb_opt('max_tokens') ) ?>"
                            min="100" max="4000" class="small-text" />
                            <p class="description"><?php esc_html_e( '400 is a good default. Higher = longer answers & more cost.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_rate_limit"><?php esc_html_e( 'Rate Limit (requests/hour per IP)', 'ai-chatbot' ); ?></label></th>
                        <td><input type="number" id="aicb_rate_limit" name="aicb_rate_limit"
                         value="<?= esc_attr( aicb_opt('rate_limit') ) ?>"
                         min="1" max="500" class="small-text" /></td>
                     </tr>
                     <tr>
                        <th><label for="aicb_system_prompt"><?php esc_html_e( 'System Prompt', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <textarea id="aicb_system_prompt" name="aicb_system_prompt"
                            rows="5" class="large-text"><?= esc_textarea( aicb_opt('system_prompt') ) ?></textarea>
                            <p class="description"><?php esc_html_e( 'Page content is appended automatically. You don't need to mention it here.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_log_retention_days"><?php esc_html_e( 'Log Retention (days)', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="number" id="aicb_log_retention_days" name="aicb_log_retention_days"
                            value="<?= esc_attr( aicb_opt('log_retention_days') ) ?>"
                            min="0" max="365" class="small-text" />
                            <p class="description"><?php esc_html_e( 'Days to keep logs before deletion. Set to 0 to keep forever (Default: 90 days).', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── LIVE ESCALATION & HANDOVER ── -->
            <div class="aicb-section">
                <h2><?php esc_html_e( 'Live Escalation & Handover', 'ai-chatbot' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Enable Handover', 'ai-chatbot' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aicb_enable_handover" value="1" <?= checked( aicb_opt('enable_handover'), 1 ) ?> />
                                <?php esc_html_e( 'Automatically suggest human contact options when the AI cannot answer', 'ai-chatbot' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_show_footer_help_button"><?php esc_html_e( 'Show "Need human help?" Button', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="aicb_show_footer_help_button" name="aicb_show_footer_help_button" value="1" <?= checked( aicb_opt('show_footer_help_button'), 1 ) ?> />
                                <?php esc_html_e( 'Display a persistent "Need human help?" button in the chat footer that opens the contact form directly', 'ai-chatbot' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'This button provides visitors with an always-visible escape hatch to request human assistance without triggering the conversational handover flow. Works independently of the Enable Handover setting above.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_handover_apology"><?php esc_html_e( 'Apology Text (Fallback)', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="text" id="aicb_handover_apology" name="aicb_handover_apology" 
                                   value="<?= esc_attr( aicb_opt('handover_apology') ) ?>" class="large-text" />
                            <p class="description"><?php esc_html_e( 'Used when the AI cannot find an answer in the database.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_handover_prompt"><?php esc_html_e( 'Escalation Prompt (Explicit Request)', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="text" id="aicb_handover_prompt" name="aicb_handover_prompt" 
                                   value="<?= esc_attr( aicb_opt('handover_prompt') ) ?>" class="large-text" />
                            <p class="description"><?php esc_html_e( 'Used when a visitor explicitly asks to talk to a human or representative.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_handover_trigger_phrases"><?php esc_html_e( 'Custom Trigger Phrases', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <textarea id="aicb_handover_trigger_phrases" name="aicb_handover_trigger_phrases" rows="6" class="large-text"><?= esc_textarea( aicb_opt('handover_trigger_phrases') ) ?></textarea>
                            <p class="description"><?php esc_html_e( 'One phrase per line. When a visitor\'s message contains any of these phrases (case-insensitive), the handover/contact form will be triggered automatically. Use for business-specific terms like "refund request", "cancel subscription", "speak to an attorney".', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_handover_type"><?php esc_html_e( 'Escalation Channel', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <select id="aicb_handover_type" name="aicb_handover_type">
                                <option value="whatsapp" <?= selected( aicb_opt('handover_type'), 'whatsapp', false ) ?>><?php esc_html_e( 'WhatsApp', 'ai-chatbot' ); ?></option>
                                <option value="tel"      <?= selected( aicb_opt('handover_type'), 'tel',      false ) ?>><?php esc_html_e( 'Phone Call', 'ai-chatbot' ); ?></option>
                                <option value="sms"      <?= selected( aicb_opt('handover_type'), 'sms',      false ) ?>><?php esc_html_e( 'SMS Text', 'ai-chatbot' ); ?></option>
                                <option value="custom"   <?= selected( aicb_opt('handover_type'), 'custom',   false ) ?>><?php esc_html_e( 'Custom Link', 'ai-chatbot' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_handover_target"><?php esc_html_e( 'Escalation Destination', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="text" id="aicb_handover_target" name="aicb_handover_target" 
                                   value="<?= esc_attr( aicb_opt('handover_target') ) ?>" class="regular-text" 
                                   placeholder="<?php esc_attr_e( 'e.g. +1234567890 or https://example.com', 'ai-chatbot' ); ?>" />
                            <p class="description"><?php esc_html_e( 'For WhatsApp, Phone, or SMS, enter the complete international phone number (digits only, e.g., +15551234567).', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_handover_btn_text"><?php esc_html_e( 'Primary Button Text', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="text" id="aicb_handover_btn_text" name="aicb_handover_btn_text" 
                                   value="<?= esc_attr( aicb_opt('handover_btn_text') ) ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_contact_btn_text"><?php esc_html_e( 'Secondary Button Text', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="text" id="aicb_contact_btn_text" name="aicb_contact_btn_text" 
                                   value="<?= esc_attr( aicb_opt('contact_btn_text') ) ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_contact_btn_url"><?php esc_html_e( 'Secondary Button URL (Contact Page)', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="url" id="aicb_contact_btn_url" name="aicb_contact_btn_url" 
                                   value="<?= esc_url( aicb_opt('contact_btn_url') ) ?>" class="regular-text" 
                                   placeholder="https://example.com/contact" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Handover Button Styling', 'ai-chatbot' ); ?></th>
                        <td>
                            <fieldset style="display:flex;gap:20px;align-items:center">
                                <label>
                                    <?php esc_html_e( 'Primary Text', 'ai-chatbot' ); ?><br>
                                    <input type="color" name="aicb_handover_primary_text" value="<?= esc_attr( aicb_opt('handover_primary_text') ) ?>" />
                                </label>
                                <label>
                                    <?php esc_html_e( 'Secondary BG', 'ai-chatbot' ); ?><br>
                                    <input type="color" name="aicb_handover_secondary_bg" value="<?= esc_attr( aicb_opt('handover_secondary_bg') ) ?>" />
                                </label>
                                <label>
                                    <?php esc_html_e( 'Secondary Text', 'ai-chatbot' ); ?><br>
                                    <input type="color" name="aicb_handover_secondary_text" value="<?= esc_attr( aicb_opt('handover_secondary_text') ) ?>" />
                                </label>
                                <label>
                                    <?php esc_html_e( 'Border Radius (px)', 'ai-chatbot' ); ?><br>
                                    <input type="number" name="aicb_handover_btn_radius" value="<?= esc_attr( aicb_opt('handover_btn_radius') ) ?>" min="0" max="50" class="small-text" />
                                </label>
                            </fieldset>
                            <p style="margin-top:12px;">
                                <label>
                                    <input type="checkbox" name="aicb_always_show_handover_buttons" value="1" <?= checked( aicb_opt('always_show_handover_buttons'), 1 ) ?> />
                                    <strong><?= esc_html__( 'Always show buttons in chat window?', 'ai-chatbot' ); ?></strong> <?= esc_html__( '(Will display at the bottom of the chat window, below the message input box)', 'ai-chatbot' ); ?>
                                </label>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── LEAD CAPTURE & TRANSCRIPT ── -->
            <div class="aicb-section">
                <h2><?php esc_html_e( 'Lead Capture & Transcript Export', 'ai-chatbot' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Enable Lead Capture', 'ai-chatbot' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aicb_enable_lead_capture" value="1" <?= checked( aicb_opt('enable_lead_capture'), 1 ) ?> />
                                <?php esc_html_e( 'Show a contact form in the chat when handover is confirmed (visitor can leave name & email)', 'ai-chatbot' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Leads are stored in the database and can be viewed under the Leads menu.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="aicb_lead_notification_email"><?php esc_html_e( 'Notification Email', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <input type="email" id="aicb_lead_notification_email" name="aicb_lead_notification_email"
                                   value="<?= esc_attr( aicb_opt('lead_notification_email') ) ?>" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Optional: admin@example.com', 'ai-chatbot' ); ?>" />
                            <p class="description"><?php esc_html_e( 'If set, an email notification will be sent when a new lead is submitted. Leave blank to only store in the database.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Enable Transcript Export', 'ai-chatbot' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aicb_enable_transcript_export" value="1" <?= checked( aicb_opt('enable_transcript_export'), 1 ) ?> />
                                <?php esc_html_e( 'Show an "Email transcript" button in the chat footer so visitors can email themselves a copy of the conversation', 'ai-chatbot' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Conversation history is retrieved from the chat logs database.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- ── TAB 5: ADVANCED & MIGRATION ── -->
        <div id="tab-advanced-migration" class="aicb-tab-panel">
            <!-- ── DYNAMIC ADVANCED PROMPT ENGINEERING PANEL ── -->
            <div id="aicb-advanced-prompts-panel" class="aicb-section">
                <h2><?php esc_html_e( 'Advanced Prompt Engineering Templates', 'ai-chatbot' ); ?></h2>
                <p style="color:#555;margin-top:-8px"><?php esc_html_e( "Exposes the granular sub-prompts used to coordinate and instruct the model's factual, temporal, and tool-calling processes.", 'ai-chatbot' ); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th style="width:200px;"><label for="prompt_temporal_pivot"><?php esc_html_e( 'Temporal Context Prompt', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <textarea id="prompt_temporal_pivot" name="aicb_prompt_temporal_pivot" rows="3" class="large-text" required><?= esc_textarea( aicb_opt('prompt_temporal_pivot') ) ?></textarea>
                            <p class="description"><?php esc_html_e( 'Instructions guiding how current system date details are presented. Supports {current_date} and {current_time} dynamic tokens.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="prompt_tool_instruction"><?php esc_html_e( 'Tool Coordination Prompt', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <textarea id="prompt_tool_instruction" name="aicb_prompt_tool_instruction" rows="8" class="large-text" required><?= esc_textarea( aicb_opt('prompt_tool_instruction') ) ?></textarea>
                            <p class="description"><?php esc_html_e( "The explicit instructions coordinating when the model should invoke the calendar tool versus standard business FAQs.", 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="prompt_negative_constraints"><?php esc_html_e( 'Negative Constraints Prompt', 'ai-chatbot' ); ?></label></th>
                        <td>
                            <textarea id="prompt_negative_constraints" name="aicb_prompt_negative_constraints" rows="10" class="large-text" required><?= esc_textarea( aicb_opt('prompt_negative_constraints') ) ?></textarea>
                            <p class="description"><?php esc_html_e( 'Explicit formatting boundaries, context safeguards, word exclusions, and response structural limits.', 'ai-chatbot' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Reset prompts directly inside settings form context safely -->
                <div style="border-top:1px solid #eee;margin-top:20px;padding-top:20px">
                    <p class="description" style="margin-bottom:10px; color:#b91c1c;">⚠️ <?php esc_html_e( 'Warning: Resetting will instantly restore all prompt textareas above back to their original core templates.', 'ai-chatbot' ); ?></p>
                    <button type="button" id="aicb-reset-prompts-btn" class="button button-secondary" style="color: #b91c1c; border-color: #f87171;"><?php esc_html_e( 'Reset Engineering Templates', 'ai-chatbot' ); ?></button>
                </div>
            </div>
        </div>

        <div class="aicb-settings-submit-wrapper">
            <?php submit_button( __( 'Save Settings', 'ai-chatbot' ) ); ?>
        </div>
    </form>

    <form id="aicb-reset-prompts-form" method="post" style="display:none;">
        <?php wp_nonce_field( 'aicb_reset_prompts', 'aicb_reset_prompts_nonce' ); ?>
    </form>

    <form id="aicb-flush-cache-form" method="post" onsubmit="return confirm('<?php esc_attr_e( 'Flush all cached summaries? They will be lazy-regenerated on-demand.', 'ai-chatbot' ); ?>')">
        <?php wp_nonce_field( 'aicb_flush_cache', 'aicb_flush_cache_nonce' ); ?>
    </form>

    <!-- ── TAB 5 EXTERNAL CONTAINER: IMPORT / EXPORT CONFIGURATION ── -->
    <div id="tab-advanced-migration-external" style="">
        <div class="aicb-section">
            <h2><?php esc_html_e( 'Import / Export Configuration', 'ai-chatbot' ); ?></h2>

            <div class="aicb-notice" style="border-left-color:#d97706;">
                <strong>🔒 <?php esc_html_e( 'Security Notice:', 'ai-chatbot' ); ?></strong> <?php esc_html_e( 'API keys are never included in the export for security reasons. After importing, you will need to re-enter your API keys manually.', 'ai-chatbot' ); ?>
            </div>

            <!-- ── EXPORT ── -->
            <h3 style="margin-bottom:8px"><?php esc_html_e( 'Export', 'ai-chatbot' ); ?></h3>
            <p class="description" style="margin-top:0"><?php esc_html_e( 'Select the sections to include in the export file, then click Export.', 'ai-chatbot' ); ?></p>

            <form method="post" action="" style="margin-bottom:18px">
                <?php wp_nonce_field( 'aicb_export_settings', 'aicb_export_nonce' ); ?>

                <label style="display:block;margin-bottom:8px">
                    <input type="checkbox" name="aicb_export_general" value="1" />
                    <strong><?php esc_html_e( 'General Settings', 'ai-chatbot' ); ?></strong> — <?php esc_html_e( 'Provider, model, display, behavior, handover, persona, language, cache, feedback, and all other standard settings', 'ai-chatbot' ); ?>
                </label>

                <label style="display:block;margin-bottom:8px">
                    <input type="checkbox" name="aicb_export_calendar" value="1" />
                    <strong><?php esc_html_e( 'Calendar & Hours', 'ai-chatbot' ); ?></strong> — <?php esc_html_e( 'All calendar entries, default hours, and weekend configuration', 'ai-chatbot' ); ?>
                </label>

                <label style="display:block;margin-bottom:8px">
                    <input type="checkbox" name="aicb_export_prompts" value="1" />
                    <strong><?php esc_html_e( 'Advanced Prompt Engineering', 'ai-chatbot' ); ?></strong> — <?php esc_html_e( 'System prompt, temporal context, tool coordination, and negative constraints templates', 'ai-chatbot' ); ?>
                </label>

                <label style="display:block;margin-bottom:8px">
                    <input type="checkbox" name="aicb_export_qa" value="1" />
                    <strong><?php esc_html_e( 'Custom Q&A Entries', 'ai-chatbot' ); ?></strong> — <?php esc_html_e( 'All entries from the Custom Q&A database table', 'ai-chatbot' ); ?>
                </label>

                <label style="display:block;margin-bottom:8px">
                    <input type="checkbox" name="aicb_export_models" value="1" />
                    <strong><?php esc_html_e( 'Custom Model Definitions', 'ai-chatbot' ); ?></strong> — <?php esc_html_e( 'User-added models from the Models page', 'ai-chatbot' ); ?>
                    <span style="color:#d97706"><?php esc_html_e( '(API keys are excluded)', 'ai-chatbot' ); ?></span>
                </label>

                <p style="margin-top:16px">
                    <button type="submit" class="button button-primary"><?php esc_html_e( '📥 Export Configuration', 'ai-chatbot' ); ?></button>
                </p>
            </form>

            <hr style="margin:24px 0">

            <!-- ── IMPORT ── -->
            <h3 style="margin-bottom:8px"><?php esc_html_e( 'Import', 'ai-chatbot' ); ?></h3>
            <p class="description" style="margin-top:0"><?php esc_html_e( 'Upload a previously exported .json file to restore your settings.', 'ai-chatbot' ); ?></p>

            <div class="aicb-notice" style="border-left-color:#b91c1c;margin-bottom:16px">
                <strong>⚠️ <?php esc_html_e( 'Warning:', 'ai-chatbot' ); ?></strong> <?php esc_html_e( 'Importing will overwrite your current settings for the sections present in the file. This action cannot be undone. We recommend exporting your current configuration first as a backup.', 'ai-chatbot' ); ?>
            </div>

            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'aicb_import_settings', 'aicb_import_nonce' ); ?>

                <p>
                    <input type="file" name="aicb_import_file" accept=".json" required />
                </p>
                <p>
                    <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to import this configuration? Current settings will be overwritten for the sections contained in the file.', 'ai-chatbot' ); ?>');"><?php esc_html_e( '📥 Import Configuration', 'ai-chatbot' ); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<!-- Embed catalog JSON for JS -->
<script>
(function() {
    var catalog  = <?= wp_json_encode( aicb_get_catalog() ) ?>;
    var curModel = <?= wp_json_encode( $cur_model ) ?>;
    var providerMap = {};
    catalog.providers.forEach(function(p){ providerMap[p.id] = p; });
    var grid     = document.getElementById('aicb-provider-grid');
    var modelSel = document.getElementById('aicb_model');
    var modelDesc= document.getElementById('aicb-model-desc');

    function updateModels(providerId) {
        var p = providerMap[providerId];
        if (!p) return;
        modelSel.innerHTML = '';
        
        // Retain and display Model Dropdown always, allowing custom model selects
        modelSel.parentElement.style.display = '';
        
        (p.models || []).forEach(function(m) {
            var opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name + (m.recommended ? ' ★' : '');
            if (m.id === curModel) opt.selected = true;
            modelSel.appendChild(opt);
        });
        updateDesc();
    }

    function updateDesc() {
        var pid = document.querySelector('input[name="aicb_provider"]:checked');
        if (!pid) return;
        var p = providerMap[pid.value];
        if (!p) return;
        var sel = modelSel.value;
        var m = (p.models || []).find(function(x){ return x.id === sel; });
        modelDesc.textContent = m ? m.description + (m.context_k ? ' · ' + m.context_k + 'k context' : '') : '';
    }

    grid.querySelectorAll('.aicb-provider-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var pid = this.dataset.provider;
            grid.querySelectorAll('.aicb-provider-card').forEach(function(c){ c.classList.remove('selected'); });
            this.classList.add('selected');
            document.querySelector('input[name="aicb_provider"][value="' + pid + '"]').checked = true;
            curModel = ''; 
            updateModels(pid);
        });
    });

    modelSel.addEventListener('change', updateDesc);
    updateModels(<?= wp_json_encode( $cur_provider ) ?>);
    updateDesc();

    // jQuery Tab-Switching and Prompt UI Controls
    jQuery(document).ready(function($) {
        // Safe SessionStorage retrieval to prevent SecurityError in restricted browsers/iframes
        var activeTab = 'tab-ai-engine';
        try {
            activeTab = sessionStorage.getItem('aicb_active_tab') || 'tab-ai-engine';
        } catch (e) {
            console.warn('SessionStorage block detected, falling back to default tab.', e);
        }

        function switchTab(tabId) {
            $('.aicb-tab-link').removeClass('active');
            $('.aicb-tab-link[data-tab="' + tabId + '"]').addClass('active');
            $('.aicb-tab-panel').removeClass('active').hide();
            $('#' + tabId).addClass('active').show();

            // Toggle import/export which lives outside the main form wrapper
            if (tabId === 'tab-advanced-migration') {
                $('#tab-advanced-migration-external').show();
            } else {
                $('#tab-advanced-migration-external').hide();
            }

            try {
                sessionStorage.setItem('aicb_active_tab', tabId);
            } catch (e) {}
        }

        // Active progressive enhancement once jQuery loads successfully
        $('.aicb-wrap').addClass('aicb-js-active');

        $('.aicb-tab-link').on('click', function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');
            switchTab(tabId);
        });

        // Initialize display configuration state safely
        switchTab(activeTab);

        // Reset system prompt engineering templates
        $('#aicb-reset-prompts-btn').on('click', function(e) {
            e.preventDefault();
            if (confirm('<?php esc_attr_e( 'Are you sure you want to reset all prompt engineering templates to default? This cannot be undone.', 'ai-chatbot' ); ?>')) {
                $('#aicb-reset-prompts-form').submit();
            }
        });

        // Toggle fixed language row on mode switch
        $('#aicb_chatbot_language_mode').on('change', function() {
            if ($(this).val() === 'fixed') {
                $('#aicb-fixed-language-row').show();
            } else {
                $('#aicb-fixed-language-row').hide();
            }
        });
    });
})();
</script>