<div class="wrap aicb-wrap">
    <h1>Leads</h1>
    <p>Lead submissions from visitors who requested contact through the chat widget.</p>

    <?php if ( $unread_count > 0 ) : ?>
        <div class="aicb-notice">
            <strong><?php echo (int) $unread_count; ?> unread lead(s)</strong>
        </div>
    <?php endif; ?>

    <?php if ( empty( $rows ) ) : ?>
        <div class="aicb-section">
            <p>No leads yet. Leads will appear here when visitors submit the contact form in the chat widget.</p>
        </div>
    <?php else : ?>
        <form method="post" style="margin-bottom: 16px;">
            <?php wp_nonce_field( 'aicb_leads_action', 'aicb_leads_nonce' ); ?>
            <input type="hidden" name="aicb_action" value="delete_all" />
            <button type="submit" class="button button-secondary" onclick="return confirm('Delete all leads permanently?');">Delete All Leads</button>
        </form>

        <table class="wp-list-table widefat fixed striped aicb-logs">
            <thead>
                <tr>
                    <th scope="col" style="width: 30px;">Status</th>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Message</th>
                    <th scope="col" style="width: 140px;">Date</th>
                    <th scope="col" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $lead ) : ?>
                    <tr>
                        <td>
                            <?php if ( ! $lead->read_status ) : ?>
                                <span style="display:inline-block;width:10px;height:10px;background:#2563eb;border-radius:50%;" title="Unread"></span>
                            <?php else : ?>
                                <span style="color:#94a3b8;">✓</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html( $lead->name ); ?></strong></td>
                        <td><a href="mailto:<?php echo esc_attr( $lead->email ); ?>"><?php echo esc_html( $lead->email ); ?></a></td>
                        <td><?php echo esc_html( wp_html_excerpt( $lead->message, 120 ) ); ?></td>
                        <td><?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $lead->created_at ) ) ); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'aicb_leads_action', 'aicb_leads_nonce' ); ?>
                                <input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
                                <?php if ( ! $lead->read_status ) : ?>
                                    <input type="hidden" name="aicb_action" value="mark_read" />
                                    <button type="submit" class="button button-small">Mark Read</button>
                                <?php else : ?>
                                    <input type="hidden" name="aicb_action" value="mark_unread" />
                                    <button type="submit" class="button button-small">Mark Unread</button>
                                <?php endif; ?>
                            </form>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'aicb_leads_action', 'aicb_leads_nonce' ); ?>
                                <input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
                                <input type="hidden" name="aicb_action" value="delete" />
                                <button type="submit" class="button button-small" onclick="return confirm('Delete this lead?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div style="margin-top: 16px;">
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links( [
                            'base'    => add_query_arg( 'lp', '%#%' ),
                            'format'  => '',
                            'current' => $page,
                            'total'   => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ] );
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>