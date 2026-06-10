<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aicb-wrap">
    <h1>AI Chatbot — Calendar & Hours</h1>
    <p class="description">Configure business hours and special dates. The chatbot will use the <code>check_calendar</code> tool to answer opening-hours questions deterministically.</p>

    <!-- Hidden form for row-level actions to avoid nested forms -->
    <form id="aicb-single-action-form" method="post" style="display:none;">
        <?php wp_nonce_field( 'aicb_cal_action', 'aicb_cal_nonce' ); ?>
        <input type="hidden" name="aicb_action" id="aicb-single-action" value="" />
        <input type="hidden" name="entry_index" id="aicb-single-index" value="" />
    </form>

    <form method="post">
        <?php wp_nonce_field( 'aicb_cal_action', 'aicb_cal_nonce' ); ?>
        <input type="hidden" name="aicb_action" value="save_tool_status" />
        <div class="aicb-section">
            <h2>Tool-Calling Status</h2>
            <label>
                <input type="checkbox" name="enable_calendar_tools" value="1" <?= checked( $enable_tools, 1 ) ?> />
                Enable <code>check_calendar</code> tool for the AI chatbot
            </label>
            <p class="description">When enabled, the LLM will call the calendar tool instead of reasoning about dates itself.</p>
            <?php submit_button( 'Save Tool Status', 'primary', 'submit', false ); ?>
        </div>
    </form>

    <form method="post">
        <?php wp_nonce_field( 'aicb_cal_action', 'aicb_cal_nonce' ); ?>
        <input type="hidden" name="aicb_action" value="save_defaults" />
        <div class="aicb-section">
            <h2>Default Hours</h2>
            <table class="form-table">
                <tr>
                    <th>Weekdays (Mon–Fri)</th>
                    <td>
                        Open: <input type="time" name="wd_open" value="<?= esc_attr( $calendar['default_weekday_hours']['open'] ?? '09:00' ) ?>" />
                        Close: <input type="time" name="wd_close" value="<?= esc_attr( $calendar['default_weekday_hours']['close'] ?? '17:00' ) ?>" />
                    </td>
                </tr>
                <tr>
                    <th>Weekends (Sat–Sun)</th>
                    <td>
                        Open: <input type="time" name="we_open" value="<?= esc_attr( $calendar['default_weekend_hours']['open'] ?? '10:00' ) ?>" />
                        Close: <input type="time" name="we_close" value="<?= esc_attr( $calendar['default_weekend_hours']['close'] ?? '15:00' ) ?>" />
                        &nbsp; Status:
                        <select name="we_status">
                            <option value="open"   <?= selected( $calendar['default_weekend_status'] ?? 'closed', 'open' ) ?>>Open</option>
                            <option value="closed" <?= selected( $calendar['default_weekend_status'] ?? 'closed', 'closed' ) ?>>Closed</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Defaults', 'primary', 'submit', false ); ?>
        </div>
    </form>

    <div class="aicb-section">
        <h2><?= $edit_entry ? 'Edit Entry' : 'Add Entry' ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'aicb_cal_action', 'aicb_cal_nonce' ); ?>
            <input type="hidden" name="aicb_action" value="<?= $edit_entry ? 'update_entry' : 'add_entry' ?>" />
            <?php if ( $edit_entry ) : ?>
                <input type="hidden" name="entry_index" value="<?= $edit_idx ?>" />
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th>Date</th>
                    <td>
                        <?php
                        $display_input_val = '';
                        if ( $edit_entry ) {
                            $display_input_val = $edit_entry['date'];
                            if ( 0 === strpos( $display_input_val, '--' ) ) {
                                $display_input_val = str_replace( '-', '/', ltrim( $display_input_val, '-' ) );
                            } else {
                                $ts = strtotime( $display_input_val );
                                if ( $ts ) {
                                    $display_input_val = date( 'm/d/Y', $ts );
                                }
                            }
                        }
                        ?>
                        <input type="text" name="entry_date" value="<?= esc_attr( $display_input_val ) ?>" placeholder="MM/DD/YYYY" class="regular-text aicb-datepicker" autocomplete="off" readonly="readonly" style="cursor: pointer; background: #fff;" />
                        <br>
                        <label style="display:inline-block; margin-top:8px;">
                            <input type="checkbox" name="entry_is_recurring" value="1" <?= ( $edit_entry && 0 === strpos( $edit_entry['date'] ?? '', '--' ) ) ? 'checked' : '' ?> />
                            <strong>Yearly recurring event</strong> (ignores year)
                        </label>
                        <p class="description" style="margin-top:4px;">Select a date using the calendar popup. Check "Yearly recurring event" to ignore the year (such as Christmas or annual holidays).</p>
                    </td>
                </tr>
                <tr>
                    <th>Label</th>
                    <td><input type="text" name="entry_label" value="<?= $edit_entry ? esc_attr( $edit_entry['label'] ) : '' ?>" placeholder="e.g. Labor Day" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <select name="entry_status">
                            <option value="open"   <?= selected( $edit_entry['status'] ?? 'open', 'open' ) ?>>Open</option>
                            <option value="closed" <?= selected( $edit_entry['status'] ?? 'open', 'closed' ) ?>>Closed</option>
                            <option value="reduced"<?= selected( $edit_entry['status'] ?? 'open', 'reduced' ) ?>>Reduced Hours</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Hours (if overriding default)</th>
                    <td>
                        Open: <input type="time" name="entry_hours_open" value="<?= $edit_entry ? esc_attr( $edit_entry['hours_open'] ?? '' ) : '' ?>" />
                        Close: <input type="time" name="entry_hours_close" value="<?= $edit_entry ? esc_attr( $edit_entry['hours_close'] ?? '' ) : '' ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button( $edit_entry ? 'Update Entry' : 'Add Entry', 'primary', 'submit', false ); ?>
            <?php if ( $edit_entry ) : ?>
                <a href="?page=ai-chatbot-calendar" class="button">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="aicb-section" id="aicb-entry-list">
        <h2>Special Dates (<?= $total_entries ?>)</h2>
        <?php if ( $total_entries > 0 ) : ?>
            <?php
            $fixed_cnt = 0; $recur_cnt = 0;
            $closed_cnt = 0; $open_cnt = 0; $reduced_cnt = 0;
            foreach ( $entries as $e ) {
                if ( 0 === strpos( $e['date'] ?? '', '--' ) ) {
                    $recur_cnt++;
                } else {
                    $fixed_cnt++;
                }
                $st = $e['status'] ?? 'open';
                if ( 'closed' === $st ) $closed_cnt++;
                elseif ( 'reduced' === $st ) $reduced_cnt++;
                else $open_cnt++;
            }
            ?>
            <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px;font-size:13px;color:#555">
                <span>📅 <strong><?= $fixed_cnt ?></strong> fixed · <strong><?= $recur_cnt ?></strong> recurring</span>
                <span>🔴 <strong><?= $closed_cnt ?></strong> closed · 🟢 <strong><?= $open_cnt ?></strong> open · 🟡 <strong><?= $reduced_cnt ?></strong> reduced</span>
                <?php if ( ! empty( $years ) ) : ?>
                    <span>📆 Years:
                        <a href="?page=ai-chatbot-calendar">All</a>
                        <?php foreach ( array_keys( $years ) as $y ) : ?>
                            | <a href="?page=ai-chatbot-calendar&ek_year=<?= $y ?>"<?= $filter_year === (string) $y ? ' style="font-weight:700;text-decoration:none"' : '' ?>><?= $y ?></a>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="post" id="aicb-bulk-form" onsubmit="return confirm('Delete selected entries?')">
                <?php wp_nonce_field( 'aicb_cal_action', 'aicb_cal_nonce' ); ?>
                <input type="hidden" name="aicb_action" value="delete_entries" />
                <div style="margin-bottom:10px">
                    <button class="button button-small button-link-delete" onclick="if(!confirm('Delete ALL entries?'))return false;document.getElementById('aicb-bulk-form').querySelector('input[name=\'aicb_action\']').value='clear_all_entries';return true;">🗑 Delete All</button>
                    &nbsp;
                    <button type="submit" class="button button-small" onclick="return confirm('Delete selected entries?') && document.querySelectorAll('.aicb-entry-cb:checked').length>0">Delete Selected</button>
                    &nbsp;
                    <label><input type="checkbox" id="aicb-select-all" onchange="document.querySelectorAll('.aicb-entry-cb').forEach(function(c){c.checked=this.checked},this)" /> Select All</label>
                    &nbsp;
                    <input type="text" id="aicb-filter-table" placeholder="🔍 Filter by label or date…" style="width:240px;float:right" oninput="aicbFilterEntries(this.value)" />
                </div>

                <table class="aicb-logs" id="aicb-entries-table">
                    <thead>
                        <tr>
                            <th style="width:30px"></th>
                            <th data-sort="date" class="aicb-sortable">Date <span class="aicb-sort-icon"></span></th>
                            <th data-sort="label" class="aicb-sortable">Label <span class="aicb-sort-icon"></span></th>
                            <th data-sort="status" class="aicb-sortable">Status <span class="aicb-sort-icon"></span></th>
                            <th>Hours</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $display_entries = $page_entries;
                        if ( '' !== $filter_year ) {
                            $display_entries = [];
                            foreach ( $page_entries as $i => $e ) {
                                if ( 0 === strpos( $e['date'] ?? '', $filter_year ) ) {
                                    $display_entries[ $i ] = $e;
                                }
                            }
                        }
                        ?>
                        <?php foreach ( $display_entries as $idx => $e ) : ?>
                            <tr class="aicb-entry-row" data-date="<?= esc_attr( $e['date'] ?? '' ) ?>" data-label="<?= esc_attr( $e['label'] ?? '' ) ?>" data-status="<?= esc_attr( $e['status'] ?? 'open' ) ?>">
                                <td><input type="checkbox" class="aicb-entry-cb" name="entry_indices[]" value="<?= $idx ?>" /></td>
                                <td><?= esc_html( aicb_format_date_us( $e['date'] ?? '' ) ) ?></td>
                                <td><?= esc_html( $e['label'] ?? '' ) ?></td>
                                <td><?= esc_html( $e['status'] ?? 'open' ) ?></td>
                                <td><?= ( ! empty( $e['hours_open'] ) && ! empty( $e['hours_close'] ) ) ? esc_html( $e['hours_open'] . '-' . $e['hours_close'] ) : '—' ?></td>
                                <td>
                                    <a href="?page=ai-chatbot-calendar&edit_entry=<?= $idx ?>" class="button button-small">Edit</a>
                                    <button type="button" class="button button-small button-link-delete" onclick="aicbSubmitSingleAction('delete_entry', <?= $idx ?>, 'Delete this entry?')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $total_pages > 1 ) : ?>
                    <div style="margin-top:14px" class="aicb-pagination">
                        <?php
                        $page_url = remove_query_arg( 'ek_page', add_query_arg( 'page', 'ai-chatbot-calendar', admin_url( 'admin.php' ) ) );
                        if ( '' !== $filter_year ) $page_url = add_query_arg( 'ek_year', $filter_year, $page_url );
                        ?>
                        <span style="margin-right:12px">Page <?= $paged_ek ?> of <?= $total_pages ?></span>
                        <?php if ( $paged_ek > 1 ) : ?>
                            <a class="button button-small" href="<?= esc_url( add_query_arg( 'ek_page', 1, $page_url ) ) ?>">« First</a>
                            <a class="button button-small" href="<?= esc_url( add_query_arg( 'ek_page', $paged_ek - 1, $page_url ) ) ?>">‹ Prev</a>
                        <?php endif; ?>
                        <?php if ( $paged_ek < $total_pages ) : ?>
                            <a class="button button-small" href="<?= esc_url( add_query_arg( 'ek_page', $paged_ek + 1, $page_url ) ) ?>">Next ›</a>
                            <a class="button button-small" href="<?= esc_url( add_query_arg( 'ek_page', $total_pages, $page_url ) ) ?>">Last »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php else : ?>
            <p>No special dates configured.</p>
        <?php endif; ?>
    </div>

    <!-- ── DYNAMIC GLOBAL COUNTRY HOLIDAYS SEEDER ── -->
    <div class="aicb-section">
        <h2>Seed Global Country Holidays</h2>
        <form method="post" onsubmit="return confirm('Add country holidays to the calendar for the specified year range? Existing entries will not be duplicated.');">
            <?php wp_nonce_field( 'aicb_cal_action', 'aicb_cal_nonce' ); ?>
            <input type="hidden" name="aicb_action" value="seed_holidays" />
            <p class="description">Fetches country-specific public holidays from the <a href="https://date.nager.at" target="_blank">Nager.Date API</a>. Holidays are stored as exact dates.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="seed_from_year">From Year</label></th>
                    <td>
                        <input type="number" name="seed_from_year" id="seed_from_year" value="<?= (int) current_time( 'Y' ) ?>" min="2020" max="2099" style="width:100px" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="seed_to_year">To Year</label></th>
                    <td>
                        <input type="number" name="seed_to_year" id="seed_to_year" value="<?= (int) current_time( 'Y' ) + 2 ?>" min="2020" max="2099" style="width:100px" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="seed_country_search">Country</label></th>
                    <td>
                        <input type="text" id="seed_country_search" value="United States" placeholder="Type to search country..." class="regular-text" style="width:260px;" required autocomplete="off" />
                        <!-- Hidden input to pass the parsed country ISO code to the controller -->
                        <input type="hidden" name="seed_country_code" id="seed_country_code" value="US" />
                        <p class="description">Type the country name to filter, then select it from the dropdown options.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Seed Holidays', 'secondary', 'submit', false ); ?>
        </form>
    </div>
</div>

<script>
(function() {
    // Registered single action handler to process individual row deletions securely
    window.aicbSubmitSingleAction = function(action, index, confirmMsg) {
        if (confirmMsg && !confirm(confirmMsg)) return;
        document.getElementById('aicb-single-action').value = action;
        document.getElementById('aicb-single-index').value = index;
        document.getElementById('aicb-single-action-form').submit();
    };

    var sortOrder = { 'date': 'asc', 'label': 'asc', 'status': 'asc' };
    var activeSort = null;

    document.querySelectorAll('.aicb-sortable').forEach(function(th) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            var key = this.dataset.sort;
            var tbody = document.getElementById('aicb-entries-table').querySelector('tbody');
            var rows  = Array.prototype.slice.call(tbody.querySelectorAll('.aicb-entry-row'));
            var order = sortOrder[ key ] === 'asc' ? 'desc' : 'asc';
            sortOrder[ key ] = order;
            activeSort = key;

            document.querySelectorAll('.aicb-sortable .aicb-sort-icon').forEach(function(icon){
                icon.textContent = '';
            });
            this.querySelector('.aicb-sort-icon').textContent = order === 'asc' ? ' ▲' : ' ▼';

            rows.sort(function(a, b) {
                var va = (a.dataset[key] || '').toLowerCase();
                var vb = (b.dataset[key] || '').toLowerCase();
                if ( key === 'date' ) {
                    return order === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
                }
                if ( key === 'status' ) {
                    var prio = { 'closed': 0, 'reduced': 1, 'open': 2 };
                    var pa = prio[va] !== undefined ? prio[va] : 3;
                    var pb = prio[vb] !== undefined ? prio[vb] : 3;
                    return order === 'asc' ? pa - pb : pb - pa;
                }
                return order === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
            });

            rows.forEach(function(row) { tbody.appendChild(row); });
        });
    });

    window.aicbFilterEntries = function(val) {
        var filter = val.toLowerCase();
        document.querySelectorAll('.aicb-entry-row').forEach(function(row) {
            var date  = (row.dataset.date || '').toLowerCase();
            var label = (row.dataset.label || '').toLowerCase();
            if ( date.indexOf(filter) !== -1 || label.indexOf(filter) !== -1 ) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    };
})();
</script>