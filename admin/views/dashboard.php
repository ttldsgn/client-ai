<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aicb-wrap">
    <h1>AI Chatbot — Dashboard</h1>
    
    <div class="aicb-cards">
        <div class="aicb-card"><div class="num"><?= $total ?></div><div class="lbl">Total Conversations</div></div>
        <div class="aicb-card"><div class="num"><?= $today ?></div><div class="lbl">Today</div></div>
        <div class="aicb-card"><div class="num"><?= $week ?></div><div class="lbl">This Week</div></div>
        <div class="aicb-card"><div class="num"><?= $qa_cnt ?></div><div class="lbl">Custom Q&A</div></div>
        <div class="aicb-card"><div class="num" style="font-size:1.1rem"><?= esc_html( $pname ) ?></div><div class="lbl">Active Provider</div></div>
    </div>

    <!-- Advanced Stats Section -->
    <div class="aicb-cards" style="margin-top:20px;">
        <div class="aicb-card" style="flex: 2; min-width:300px;">
            <h3>📊 AI Provider Usage</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Provider</th><th>Requests</th></tr></thead>
                <tbody>
                    <?php if ( ! empty( $provider_counts ) ) : ?>
                        <?php foreach ( $provider_counts as $row ) : ?>
                            <tr>
                                <td><strong><?= esc_html( ucfirst( $row->provider ?: 'Unknown' ) ) ?></strong></td>
                                <td><?= (int) $row->count ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="2">No usage data tracked yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="aicb-card" style="flex: 2; min-width:300px;">
            <h3>🔥 Top Interacted Pages</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Page Title</th><th>Queries</th></tr></thead>
                <tbody>
                    <?php if ( ! empty( $top_pages ) ) : ?>
                        <?php foreach ( $top_pages as $row ) : ?>
                            <tr>
                                <td><strong><?= esc_html( get_the_title( $row->page_id ) ?: '#' . $row->page_id ) ?></strong></td>
                                <td><?= (int) $row->count ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="2">No page data tracked yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="aicb-card" style="flex: 1; min-width:200px;">
            <h3>⚙️ System Insights</h3>
            <ul style="margin:0; padding:0; list-style:none;">
                <li style="padding:10px 0; border-bottom:1px solid #eee;">
                    🗣 <strong>Human Handovers:</strong> <span style="float:right; font-weight:bold; color:#2563eb;"><?= $handover_count ?></span>
                </li>
                <li style="padding:10px 0; border-bottom:1px solid #eee;">
                    💾 <strong>Cached Summaries:</strong> <span style="float:right; font-weight:bold; color:#16a34a;"><?= $cached_count ?></span>
                </li>
                <li style="padding:10px 0;">
                    📂 <strong>Logs Retention:</strong> <span style="float:right; font-weight:bold; color:#475569;"><?= esc_html( aicb_opt( 'log_retention_days' ) ?: 'Infinite' ) ?> Days</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Recent Conversations Table -->
    <div class="aicb-section" style="margin-top:20px;">
        <h2>Recent Conversations</h2>
        <?php if ( $recent ) : ?>
            <table class="aicb-logs">
                <thead><tr><th>Question</th><th>Answer</th><th>Provider</th><th>Time</th></tr></thead>
                <tbody>
                    <?php foreach ( $recent as $row ) : ?>
                        <tr>
                            <td><?= esc_html( mb_strimwidth( $row->question, 0, 80, '…' ) ) ?></td>
                            <td><?= esc_html( mb_strimwidth( $row->answer,   0, 100, '…' ) ) ?></td>
                            <td><span class="aicb-tag aicb-tag-provider"><?= esc_html( $row->provider ?: '—' ) ?></span></td>
                            <td><?= esc_html( human_time_diff( strtotime( $row->created_at ), time() ) ) ?> ago</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:15px;"><a href="?page=ai-chatbot-logs" class="button button-secondary">View all logs →</a></p>
        <?php else : ?>
            <p>No conversations yet.</p>
        <?php endif; ?>
    </div>
</div>