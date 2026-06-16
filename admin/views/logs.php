<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aicb-wrap">
    <?php if ( ! empty( $_GET['view_session'] ) && isset( $messages ) ) : ?>
        <!-- Transcript Detail View -->
        <h1>
            <a href="?page=ai-chatbot-logs" style="text-decoration:none;color:#2563eb">&larr; Chat Logs</a>
            — Transcript
        </h1>
        <?php if ( $session_info ) : ?>
            <p style="color:#64748b;font-size:13px;margin-top:-8px">
                <?= (int) $session_info->msg_count ?> messages
                &middot; Started <?= esc_html( $session_info->started ) ?>
                &middot; Provider: <?= esc_html( $session_info->provider ?: '—' ) ?>
            </p>
        <?php endif; ?>

        <div class="aicb-section">
            <div class="aicb-transcript">
                <?php foreach ( $messages as $msg ) : ?>
                    <div class="aicb-transcript-msg user">
                        <span class="msg-meta">Visitor &middot; <?= esc_html( $msg->created_at ) ?></span>
                        <div class="msg-bubble"><?= esc_html( $msg->question ) ?></div>
                    </div>
                    <div class="aicb-transcript-msg bot">
                        <span class="msg-meta">AI &middot; <?= esc_html( $msg->created_at ) ?></span>
                        <div class="msg-bubble"><?= esc_html( $msg->answer ) ?></div>
                        <div class="msg-feedback"><?php
                            if ( $msg->feedback === '1' ) {
                                echo '👍 Helpful';
                            } elseif ( $msg->feedback === '0' ) {
                                echo '👎 Not helpful';
                            }
                        ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else : ?>
        <!-- List View -->
        <h1>Client AI — Chat Logs
            <form method="post" style="display:inline;float:right" onsubmit="return confirm('Clear ALL chat logs and conversation data? This cannot be undone.')">
                <?php wp_nonce_field( 'aicb_clear_logs', 'aicb_clear_nonce' ); ?>
                <button class="button button-link-delete">Clear All Data</button>
            </form>
        </h1>
        <div style="clear:both"></div>

        <!-- Chat Logs Section -->
        <div class="aicb-section">
            <h2>Chat Logs</h2>
            <?php if ( $log_rows ) : ?>
                <table class="aicb-logs">
                    <thead><tr><th>Time</th><th>Question</th><th>Answer</th><th>Provider</th><th>Model</th><th>Page</th><th>Feedback</th></tr></thead>
                    <tbody>
                        <?php foreach ( $log_rows as $row ) : ?>
                            <tr>
                                <td style="white-space:nowrap"><?= esc_html( $row->created_at ) ?></td>
                                <td><?= esc_html( $row->question ) ?></td>
                                <td><?= esc_html( mb_strimwidth( $row->answer, 0, 140, '…' ) ) ?></td>
                                <td><span class="aicb-tag aicb-tag-provider"><?= esc_html( $row->provider ?: '—' ) ?></span></td>
                                <td style="font-size:11px;color:#666"><?= esc_html( mb_strimwidth( $row->model, 0, 30, '…' ) ) ?></td>
                                <td><?php echo $row->page_id ? esc_html( get_the_title( $row->page_id ) ?: '#' . $row->page_id ) : '' ?></td>
                                <td style="text-align:center;font-size:16px"><?php
                                    if ( $row->feedback === '1' ) {
                                        echo '👍';
                                    } elseif ( $row->feedback === '0' ) {
                                        echo '👎';
                                    } else {
                                        echo '<span style="color:#cbd5e1;">—</span>';
                                    }
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( $log_pages > 1 ) : ?>
                    <div style="margin-top:10px">
                        <?php for ( $p = 1; $p <= $log_pages; $p++ ) : ?>
                            <?= $p === $log_page ? "<strong>$p</strong>" : "<a href='?page=ai-chatbot-logs&log_page=$p'>$p</a>" ?>
                            &nbsp;
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                <p style="color:#94a3b8;font-size:12px;margin-top:8px"><?= $log_total ?> total log entries.</p>
            <?php else : ?>
                <p>No chat logs yet.</p>
            <?php endif; ?>
        </div>

        <!-- Conversations Section -->
        <div class="aicb-section">
            <h2>Conversations</h2>
            <?php if ( ! empty( $conversations ) ) : ?>
                <table class="aicb-logs">
                    <thead>
                        <tr><th>Session</th><th>Messages</th><th>First Question</th><th>Started</th><th>Last Activity</th><th>Provider</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $conversations as $conv ) : ?>
                            <tr>
                                <td>
                                    <a href="?page=ai-chatbot-logs&view_session=<?= esc_attr( $conv->session_id ) ?>" style="font-weight:600;text-decoration:none;color:#2563eb">
                                        <?= esc_html( substr( $conv->session_id, 0, 12 ) ) ?>…
                                    </a>
                                </td>
                                <td><?= (int) $conv->msg_count ?></td>
                                <td><span class="aicb-conv-preview"><?= esc_html( mb_strimwidth( $conv->first_question, 0, 60, '…' ) ) ?></span></td>
                                <td style="white-space:nowrap"><?= esc_html( $conv->started ) ?></td>
                                <td style="white-space:nowrap"><?= esc_html( $conv->ended ) ?></td>
                                <td><span class="aicb-tag aicb-tag-provider"><?= esc_html( $conv->provider ?: '—' ) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( $conv_pages > 1 ) : ?>
                    <div style="margin-top:10px">
                        <?php for ( $p = 1; $p <= $conv_pages; $p++ ) : ?>
                            <?= $p === $conv_page ? "<strong>$p</strong>" : "<a href='?page=ai-chatbot-logs&conv_page=$p'>$p</a>" ?>
                            &nbsp;
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                <p style="color:#94a3b8;font-size:12px;margin-top:8px"><?= $conv_total ?> total conversations (grouped by session).</p>
            <?php else : ?>
                <p>No conversations recorded yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>