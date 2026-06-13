<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aicb-wrap">
    <h1>AI Chatbot — Settings</h1>
    
    <?php settings_errors( 'aicb_options' ); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'aicb_options' ); ?>

        <!-- ── PROVIDER & MODEL ── -->
        <div class="aicb-section">
            <h2>AI Provider & Model</h2>
            <p style="color:#555;margin-top:-8px">Select your provider, then choose a model. Each provider uses its own API key.</p>

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
                    $is_custom = ( $pid === 'custom' );
                    $active    = ( $pid === $cur_provider ) ? ' active' : '';

                    $has_const = defined( 'AICB_KEY_' . strtoupper( $pid ) );
                    $disabled  = $has_const ? ' disabled' : '';

                    if ( $has_const ) {
                        $display_val = 'Defined securely inside wp-config.php';
                    } else {
                        $stored_key  = aicb_get_key( $pid );
                        $display_val = ! empty( $stored_key ) ? 'XXXXXXXXXXXXXXXX' : '';
                    }

                    $placeholder = $has_const ? 'Configured by wp-config constants' : ( $pid === 'custom' ? 'Leave blank if not required' : 'sk-…' );
                    ?>
                    <tr class="aicb-key-row<?= $active ?>" id="aicb-keyrow-<?= esc_attr( $pid ) ?>">
                        <th style="width:200px">
                            <label for="aicb_key_<?= esc_attr( $pid ) ?>"><?= esc_html( $pdata['key_label'] ) ?></label>
                        </th>
                        <td>
                            <?php if ( $pid === 'custom' ) : ?>
                                <p style="margin:0 0 6px"><strong>Custom / OpenAI-compatible endpoint</strong></p>
                                <label style="display:block;margin-bottom:6px">
                                    Endpoint URL<br>
                                    <input type="url" name="aicb_custom_endpoint"
                                    value="<?= esc_attr( aicb_opt('custom_endpoint') ) ?>"
                                    class="regular-text" placeholder="http://localhost:11434/v1/chat/completions" />
                                </label>
                                <label style="display:block;margin-bottom:6px">
                                    Model ID<br>
                                    <input type="text" name="aicb_custom_model_id"
                                    value="<?= esc_attr( aicb_opt('custom_model_id') ) ?>"
                                    class="regular-text" placeholder="e.g. llama3, mistral, phi3" />
                                </label>
                            <?php endif; ?>
                            <input type="password" id="aicb_key_<?= esc_attr( $pid ) ?>"
                            name="aicb_key_<?= esc_attr( $pid ) ?>"
                            value="<?= esc_attr( $display_val ) ?>"
                            class="regular-text" autocomplete="off"
                            placeholder="<?= esc_attr( $placeholder ) ?>"<?= $disabled ?> />
                            <?php if ( $has_const ) : ?>
                                <p class="description" style="color: #16a34a; font-weight: 600;">✓ This key is defined as a PHP constant and cannot be modified here.</p>
                            <?php elseif ( ! empty( $pdata['key_help'] ) ) : ?>
                                <p class="description"><?= esc_html( $pdata['key_help'] ) ?>
                                <?php if ( ! empty( $pdata['docs_url'] ) ) : ?>
                                    — <a href="<?= esc_url( $pdata['docs_url'] ) ?>" target="_blank" rel="noopener">Docs ↗</a>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <tr>
                <th><label for="aicb_model">Model</label></th>
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

    <!-- ── KNOWLEDGE BASE & CACHING ── -->
    <div class="aicb-section">
        <h2>Knowledge Base & Caching</h2>
        <table class="form-table">
            <tr>
                <th>Enable Caching</th>
                <td>
                    <label>
                        <input type="checkbox" name="aicb_enable_cache" value="1" <?= checked( aicb_opt('enable_cache'), 1 ) ?> />
                        Cache condensed summaries of pages to reduce API token usage (Recommended)
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_cache_duration">Cache Duration</label></th>
                <td>
                    <select id="aicb_cache_duration" name="aicb_cache_duration">
                        <option value="0"    <?= selected( aicb_opt('cache_duration'), 0,    false ) ?>>Until Content Changes (Infinite)</option>
                        <option value="720"  <?= selected( aicb_opt('cache_duration'), 720,  false ) ?>>30 Days</option>
                        <option value="168"  <?= selected( aicb_opt('cache_duration'), 168,  false ) ?>>7 Days</option>
                        <option value="24"   <?= selected( aicb_opt('cache_duration'), 24,   false ) ?>>24 Hours</option>
                        <option value="1"    <?= selected( aicb_opt('cache_duration'), 1,    false ) ?>>1 Hour</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Indexing Mode</th>
                <td>
                    <label style="display:block;margin-bottom:6px">
                        <input type="radio" name="aicb_indexing_mode" value="opt-out" <?= checked( aicb_opt('indexing_mode'), 'opt-out' ) ?> />
                        <strong>Include all pages (Opt-Out)</strong> — Pages are indexed unless manually excluded in the editor.
                    </label>
                    <label style="display:block">
                        <input type="radio" name="aicb_indexing_mode" value="opt-in" <?= checked( aicb_opt('indexing_mode'), 'opt-in' ) ?> />
                        <strong>Only selected pages (Opt-In)</strong> — Pages are ignored unless manually included in the editor.
                    </label>
                </td>
            </tr>
            <tr>
                <th>Allowed Post Types</th>
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
            <p class="description" style="margin-bottom:10px">If you updated your global system prompt or changed model providers, we recommend flushing the cache to force a regeneration of factual summaries.</p>
            <button type="submit" form="aicb-flush-cache-form" class="button button-secondary">Flush All Cached Summaries</button>
        </div>
    </div>

    <!-- ── LIVE ESCALATION & HANDOVER ── -->
    <div class="aicb-section">
        <h2>Live Escalation & Handover</h2>
        <table class="form-table">
            <tr>
                <th>Enable Handover</th>
                <td>
                    <label>
                        <input type="checkbox" name="aicb_enable_handover" value="1" <?= checked( aicb_opt('enable_handover'), 1 ) ?> />
                        Automatically suggest human contact options when the AI cannot answer
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_handover_apology">Apology Text (Fallback)</label></th>
                <td>
                    <input type="text" id="aicb_handover_apology" name="aicb_handover_apology" 
                           value="<?= esc_attr( aicb_opt('handover_apology') ) ?>" class="large-text" />
                    <p class="description">Used when the AI cannot find an answer in the database.</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_handover_prompt">Escalation Prompt (Explicit Request)</label></th>
                <td>
                    <input type="text" id="aicb_handover_prompt" name="aicb_handover_prompt" 
                           value="<?= esc_attr( aicb_opt('handover_prompt') ) ?>" class="large-text" />
                    <p class="description">Used when a visitor explicitly asks to talk to a human or representative.</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_handover_type">Escalation Channel</label></th>
                <td>
                    <select id="aicb_handover_type" name="aicb_handover_type">
                        <option value="whatsapp" <?= selected( aicb_opt('handover_type'), 'whatsapp', false ) ?>>WhatsApp</option>
                        <option value="tel"      <?= selected( aicb_opt('handover_type'), 'tel',      false ) ?>>Phone Call</option>
                        <option value="sms"      <?= selected( aicb_opt('handover_type'), 'sms',      false ) ?>>SMS Text</option>
                        <option value="custom"   <?= selected( aicb_opt('handover_type'), 'custom',   false ) ?>>Custom Link</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_handover_target">Escalation Destination</label></th>
                <td>
                    <input type="text" id="aicb_handover_target" name="aicb_handover_target" 
                           value="<?= esc_attr( aicb_opt('handover_target') ) ?>" class="regular-text" 
                           placeholder="e.g. +1234567890 or https://example.com" />
                    <p class="description">For WhatsApp, Phone, or SMS, enter the complete international phone number (digits only, e.g., +15551234567).</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_handover_btn_text">Primary Button Text</label></th>
                <td>
                    <input type="text" id="aicb_handover_btn_text" name="aicb_handover_btn_text" 
                           value="<?= esc_attr( aicb_opt('handover_btn_text') ) ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="aicb_contact_btn_text">Secondary Button Text</label></th>
                <td>
                    <input type="text" id="aicb_contact_btn_text" name="aicb_contact_btn_text" 
                           value="<?= esc_attr( aicb_opt('contact_btn_text') ) ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="aicb_contact_btn_url">Secondary Button URL (Contact Page)</label></th>
                <td>
                    <input type="url" id="aicb_contact_btn_url" name="aicb_contact_btn_url" 
                           value="<?= esc_url( aicb_opt('contact_btn_url') ) ?>" class="regular-text" 
                           placeholder="https://example.com/contact" />
                </td>
            </tr>
            <tr>
                <th>Handover Button Styling</th>
                <td>
                    <fieldset style="display:flex;gap:20px;align-items:center">
                        <label>
                            Primary Text<br>
                            <input type="color" name="aicb_handover_primary_text" value="<?= esc_attr( aicb_opt('handover_primary_text') ) ?>" />
                        </label>
                        <label>
                            Secondary BG<br>
                            <input type="color" name="aicb_handover_secondary_bg" value="<?= esc_attr( aicb_opt('handover_secondary_bg') ) ?>" />
                        </label>
                        <label>
                            Secondary Text<br>
                            <input type="color" name="aicb_handover_secondary_text" value="<?= esc_attr( aicb_opt('handover_secondary_text') ) ?>" />
                        </label>
                        <label>
                            Border Radius (px)<br>
                            <input type="number" name="aicb_handover_btn_radius" value="<?= esc_attr( aicb_opt('handover_btn_radius') ) ?>" min="0" max="50" class="small-text" />
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── AI PERSONA & IDENTITY ── -->
    <div class="aicb-section">
        <h2>AI Persona & Identity</h2>
        <table class="form-table">
            <tr>
                <th><label for="aicb_business_name">Entity / Business Name</label></th>
                <td>
                    <input type="text" id="aicb_business_name" name="aicb_business_name" 
                           value="<?= esc_attr( aicb_opt('business_name') ) ?>" class="regular-text" 
                           placeholder="e.g. Human Made" />
                    <p class="description">Explicitly anchors the AI's identity so it never hallucinates your brand name from website taglines.</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_pronoun_perspective">Pronouns / Perspective</label></th>
                <td>
                    <select id="aicb_pronoun_perspective" name="aicb_pronoun_perspective">
                        <option value="first-plural"   <?= selected( aicb_opt('pronoun_perspective'), 'first-plural',   false ) ?>>First-Person Plural (We / Our / Us) — For Teams & Agencies</option>
                        <option value="first-singular" <?= selected( aicb_opt('pronoun_perspective'), 'first-singular', false ) ?>>First-Person Singular (I / My / Me) — For Solo Freelancers</option>
                        <option value="neutral"        <?= selected( aicb_opt('pronoun_perspective'), 'neutral',        false ) ?>>Neutral Third-Person (The Company / The Service)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_chatbot_tone">Tone Presets</label></th>
                <td>
                    <select id="aicb_chatbot_tone" name="aicb_chatbot_tone">
                        <option value="casual"       <?= selected( aicb_opt('chatbot_tone'), 'casual',       false ) ?>>Casual (Warm, approachable, and conversational)</option>
                        <option value="professional" <?= selected( aicb_opt('chatbot_tone'), 'professional', false ) ?>>Professional (Polite, direct, and authoritative)</option>
                        <option value="minimalist"   <?= selected( aicb_opt('chatbot_tone'), 'minimalist',   false ) ?>>Minimalist (Extremely brief, factual, and objective)</option>
                    </select>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── LANGUAGE & LOCALIZATION ── -->
    <div class="aicb-section">
        <h2>Language & Localization</h2>
        <table class="form-table">
            <tr>
                <th><label for="aicb_chatbot_language_mode">Chatbot Language</label></th>
                <td>
                    <select id="aicb_chatbot_language_mode" name="aicb_chatbot_language_mode">
                        <option value="auto" <?= selected( aicb_opt('chatbot_language_mode'), 'auto', false ) ?>>Auto-detect (visitor browser language)</option>
                        <option value="fixed" <?= selected( aicb_opt('chatbot_language_mode'), 'fixed', false ) ?>>Fixed language (choose below)</option>
                    </select>
                    <p class="description">Auto-detect uses the visitor's browser language setting. Fixed forces all responses to a single language.</p>
                </td>
            </tr>
            <tr id="aicb-fixed-language-row" style="<?= aicb_opt('chatbot_language_mode') === 'fixed' ? '' : 'display:none' ?>">
                <th><label for="aicb_chatbot_language">Language</label></th>
                <td>
                    <select id="aicb_chatbot_language" name="aicb_chatbot_language">
                        <option value="">— Select a language —</option>
                        <option value="English" <?= selected( aicb_opt('chatbot_language'), 'English', false ) ?>>English</option>
                        <option value="Español" <?= selected( aicb_opt('chatbot_language'), 'Español', false ) ?>>Español</option>
                        <option value="Français" <?= selected( aicb_opt('chatbot_language'), 'Français', false ) ?>>Français</option>
                        <option value="Deutsch" <?= selected( aicb_opt('chatbot_language'), 'Deutsch', false ) ?>>Deutsch</option>
                        <option value="Português" <?= selected( aicb_opt('chatbot_language'), 'Português', false ) ?>>Português</option>
                        <option value="Italiano" <?= selected( aicb_opt('chatbot_language'), 'Italiano', false ) ?>>Italiano</option>
                        <option value="Nederlands" <?= selected( aicb_opt('chatbot_language'), 'Nederlands', false ) ?>>Nederlands</option>
                        <option value="日本語" <?= selected( aicb_opt('chatbot_language'), '日本語', false ) ?>>日本語</option>
                        <option value="中文 (简体)" <?= selected( aicb_opt('chatbot_language'), '中文 (简体)', false ) ?>>中文 (简体)</option>
                        <option value="中文 (繁體)" <?= selected( aicb_opt('chatbot_language'), '中文 (繁體)', false ) ?>>中文 (繁體)</option>
                        <option value="한국어" <?= selected( aicb_opt('chatbot_language'), '한국어', false ) ?>>한국어</option>
                        <option value="Русский" <?= selected( aicb_opt('chatbot_language'), 'Русский', false ) ?>>Русский</option>
                        <option value="العربية" <?= selected( aicb_opt('chatbot_language'), 'العربية', false ) ?>>العربية</option>
                        <option value="हिन्दी" <?= selected( aicb_opt('chatbot_language'), 'हिन्दी', false ) ?>>हिन्दी</option>
                        <option value="Bahasa Indonesia" <?= selected( aicb_opt('chatbot_language'), 'Bahasa Indonesia', false ) ?>>Bahasa Indonesia</option>
                        <option value="Türkçe" <?= selected( aicb_opt('chatbot_language'), 'Türkçe', false ) ?>>Türkçe</option>
                        <option value="Polski" <?= selected( aicb_opt('chatbot_language'), 'Polski', false ) ?>>Polski</option>
                        <option value="Svenska" <?= selected( aicb_opt('chatbot_language'), 'Svenska', false ) ?>>Svenska</option>
                        <option value="Dansk" <?= selected( aicb_opt('chatbot_language'), 'Dansk', false ) ?>>Dansk</option>
                        <option value="Suomi" <?= selected( aicb_opt('chatbot_language'), 'Suomi', false ) ?>>Suomi</option>
                        <option value="Norsk" <?= selected( aicb_opt('chatbot_language'), 'Norsk', false ) ?>>Norsk</option>
                        <option value="Čeština" <?= selected( aicb_opt('chatbot_language'), 'Čeština', false ) ?>>Čeština</option>
                        <option value="Română" <?= selected( aicb_opt('chatbot_language'), 'Română', false ) ?>>Română</option>
                        <option value="Magyar" <?= selected( aicb_opt('chatbot_language'), 'Magyar', false ) ?>>Magyar</option>
                        <option value="Ελληνικά" <?= selected( aicb_opt('chatbot_language'), 'Ελληνικά', false ) ?>>Ελληνικά</option>
                        <option value="Tiếng Việt" <?= selected( aicb_opt('chatbot_language'), 'Tiếng Việt', false ) ?>>Tiếng Việt</option>
                        <option value="ไทย" <?= selected( aicb_opt('chatbot_language'), 'ไทย', false ) ?>>ไทย</option>
                        <option value="עברית" <?= selected( aicb_opt('chatbot_language'), 'עברית', false ) ?>>עברית</option>
                    </select>
                    <p class="description">Use a language name the AI can understand. Language is sent to the model as an instruction in the system prompt.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── BEHAVIOUR ── -->
    <div class="aicb-section">
        <h2>Behaviour</h2>
        <table class="form-table">
            <tr>
                <th>Enable Feedback</th>
                <td>
                    <label>
                        <input type="checkbox" name="aicb_enable_feedback" value="1" <?= checked( aicb_opt('enable_feedback'), 1 ) ?> />
                        Show thumbs up/down after each response to collect visitor feedback
                    </label>
                    <p class="description">Adds a feedback column to the logs table. Dashboard shows satisfaction rate when enabled.</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_max_tokens">Max Response Tokens</label></th>
                <td>
                    <input type="number" id="aicb_max_tokens" name="aicb_max_tokens"
                    value="<?= esc_attr( aicb_opt('max_tokens') ) ?>"
                    min="100" max="4000" class="small-text" />
                    <p class="description">400 is a good default. Higher = longer answers & more cost.</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_rate_limit">Rate Limit (requests/hour per IP)</label></th>
                <td><input type="number" id="aicb_rate_limit" name="aicb_rate_limit"
                 value="<?= esc_attr( aicb_opt('rate_limit') ) ?>"
                 min="1" max="500" class="small-text" /></td>
             </tr>
             <tr>
                <th><label for="aicb_system_prompt">System Prompt</label></th>
                <td>
                    <textarea id="aicb_system_prompt" name="aicb_system_prompt"
                    rows="5" class="large-text"><?= esc_textarea( aicb_opt('system_prompt') ) ?></textarea>
                    <p class="description">Page content is appended automatically. You don't need to mention it here.</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_log_retention_days">Log Retention (days)</label></th>
                <td>
                    <input type="number" id="aicb_log_retention_days" name="aicb_log_retention_days"
                    value="<?= esc_attr( aicb_opt('log_retention_days') ) ?>"
                    min="0" max="365" class="small-text" />
                    <p class="description">Days to keep logs before deletion. Set to 0 to keep forever (Default: 90 days).</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── DISPLAY ── -->
    <div class="aicb-section">
        <h2>Display Options</h2>
        <table class="form-table">
            <tr>
                <th>Global Toggle</th>
                <td>
                    <label><input type="checkbox" name="aicb_enabled" value="1" <?= checked( aicb_opt('enabled'), 1 ) ?> />
                    Enable the chatbot</label>
                    <p class="description">When disabled, the chatbot is hidden everywhere — including pages using the <code>[ai_chatbot]</code> shortcode.</p>
                </td>
            </tr>
            <tr>
                <th>Auto-inject</th>
                <td>
                    <label><input type="checkbox" name="aicb_show_on_all" value="1" <?= checked( aicb_opt('show_on_all'), 1 ) ?> />
                    Add the chatbot to all pages automatically</label>
                    <p class="description">When unchecked, use <code>[ai_chatbot]</code> on specific pages only. Requires "Global Toggle" above to be enabled.</p>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_position">Position</label></th>
                <td>
                    <select id="aicb_position" name="aicb_position">
                        <option value="right"     <?= selected( aicb_opt('position'), 'right',     false ) ?>>Bottom Right — floating button</option>
                        <option value="left"      <?= selected( aicb_opt('position'), 'left',      false ) ?>>Bottom Left — floating button</option>
                        <option value="tab-right" <?= selected( aicb_opt('position'), 'tab-right', false ) ?>>Vertical Tab — Right edge</option>
                        <option value="tab-left"  <?= selected( aicb_opt('position'), 'tab-left',  false ) ?>>Vertical Tab — Left edge</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="aicb_primary_color">Primary Color</label></th>
                <td><input type="color" id="aicb_primary_color" name="aicb_primary_color" value="<?= esc_attr( aicb_opt('primary_color') ) ?>" /></td>
            </tr>
            <tr>
                <th>Button Icon</th>
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
                <th><label for="aicb_chat_title">Chat Title</label></th>
                <td><input type="text" id="aicb_chat_title" name="aicb_chat_title" value="<?= esc_attr( aicb_opt('chat_title') ) ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="aicb_welcome_msg">Welcome Message</label></th>
                <td><input type="text" id="aicb_welcome_msg" name="aicb_welcome_msg" value="<?= esc_attr( aicb_opt('welcome_msg') ) ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="aicb_placeholder">Input Placeholder</label></th>
                <td><input type="text" id="aicb_placeholder" name="aicb_placeholder" value="<?= esc_attr( aicb_opt('placeholder') ) ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="aicb_footer_text">Chat Footer Text</label></th>
                <td>
                    <input type="text" id="aicb_footer_text" name="aicb_footer_text" 
                           value="<?= esc_attr( aicb_opt('footer_text') ) ?>" class="regular-text" />
                    <p class="description">Leave blank to hide the footer text.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── DYNAMIC ADVANCED PROMPT ENGINEERING PANEL (COLLAPSIBLE) ── -->
    <div style="margin-top: 25px; margin-bottom: 25px;">
        <button type="button" class="aicb-advanced-toggle-btn" id="aicb-toggle-advanced-prompts">🛠️ Show Advanced Prompt Engineering Options</button>
    </div>

    <div id="aicb-advanced-prompts-panel" class="aicb-section" style="display: none;">
        <h2>Advanced Prompt Engineering Templates</h2>
        <p style="color:#555;margin-top:-8px">Exposes the granular sub-prompts used to coordinate and instruct the model's factual, temporal, and tool-calling processes.</p>
        
        <table class="form-table">
            <tr>
                <th style="width:200px;"><label for="prompt_temporal_pivot">Temporal Context Prompt</label></th>
                <td>
                    <textarea id="prompt_temporal_pivot" name="aicb_prompt_temporal_pivot" rows="3" class="large-text" required><?= esc_textarea( aicb_opt('prompt_temporal_pivot') ) ?></textarea>
                    <p class="description">Instructions guiding how current system date details are presented. Supports <code>{current_date}</code> and <code>{current_time}</code> dynamic tokens.</p>
                </td>
            </tr>
            <tr>
                <th><label for="prompt_tool_instruction">Tool Coordination Prompt</label></th>
                <td>
                    <textarea id="prompt_tool_instruction" name="aicb_prompt_tool_instruction" rows="8" class="large-text" required><?= esc_textarea( aicb_opt('prompt_tool_instruction') ) ?></textarea>
                    <p class="description">The explicit instructions coordinating when the model should invoke the calendar tool versus standard business FAQs.</p>
                </td>
            </tr>
            <tr>
                <th><label for="prompt_negative_constraints">Negative Constraints Prompt</label></th>
                <td>
                    <textarea id="prompt_negative_constraints" name="aicb_prompt_negative_constraints" rows="10" class="large-text" required><?= esc_textarea( aicb_opt('prompt_negative_constraints') ) ?></textarea>
                    <p class="description">Explicit formatting boundaries, context safeguards, word exclusions, and response structural limits.</p>
                </td>
            </tr>
        </table>

        <!-- Reset prompts directly inside settings form context safely -->
        <div style="border-top:1px solid #eee;margin-top:20px;padding-top:20px">
            <p class="description" style="margin-bottom:10px; color:#b91c1c;">⚠️ Warning: Resetting will instantly restore all prompt textareas above back to their original core templates.</p>
            <button type="button" id="aicb-reset-prompts-btn" class="button button-secondary" style="color: #b91c1c; border-color: #f87171;">Reset Engineering Templates</button>
        </div>
    </div>

    <?php submit_button( 'Save Settings' ); ?>
</form>

<form id="aicb-reset-prompts-form" method="post" style="display:none;">
    <?php wp_nonce_field( 'aicb_reset_prompts', 'aicb_reset_prompts_nonce' ); ?>
</form>

<form id="aicb-flush-cache-form" method="post" onsubmit="return confirm('Flush all cached summaries? They will be lazy-regenerated on-demand.')">
    <?php wp_nonce_field( 'aicb_flush_cache', 'aicb_flush_cache_nonce' ); ?>
</form>
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
        document.querySelectorAll('.aicb-key-row').forEach(function(r){ r.classList.remove('active'); });
        var keyRow = document.getElementById('aicb-keyrow-' + providerId);
        if (keyRow) keyRow.classList.add('active');
        modelSel.innerHTML = '';
        if (providerId === 'custom') {
            modelSel.parentElement.style.display = 'none';
            return;
        }
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

    // Toggle advanced prompt engineer panels dynamically
    jQuery(document).ready(function($) {
        $('#aicb-toggle-advanced-prompts').on('click', function(e) {
            e.preventDefault();
            var panel = $('#aicb-advanced-prompts-panel');
            if (panel.is(':visible')) {
                panel.slideUp(200);
                $(this).text('🛠️ Show Advanced Prompt Engineering Options');
            } else {
                panel.slideDown(200);
                $(this).text('🛠️ Hide Advanced Prompt Engineering Options');
            }
        });

        // Intercept and submit native prompt resets back to standard original values
        $('#aicb-reset-prompts-btn').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset all prompt engineering templates to default? This cannot be undone.')) {
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
