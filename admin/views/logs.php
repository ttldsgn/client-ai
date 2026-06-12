<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aicb-wrap">
    <h1>AI Chatbot — Chat Logs
        <form method="post" style="display:inline;float:right" onsubmit="return confirm('Clear ALL logs?')">
            <?php wp_nonce_field('aicb_clear_logs','aicb_clear_nonce'); ?>
            <button class="button button-link-delete">Clear All Logs</button>
        </form>
    </h1>
    <p>Total: <?= $total ?> conversations</p>
    <?php if ($rows) : ?>
        <table class="aicb-logs">
            <thead><tr><th>Time</th><th>Question</th><th>Answer</th><th>Provider</th><th>Model</th><th>Page</th><th>Feedback</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td style="white-space:nowrap"><?= esc_html($row->created_at) ?></td>
                        <td><?= esc_html($row->question) ?></td>
                        <td><?= esc_html( mb_strimwidth($row->answer,0,140,'…') ) ?></td>
                        <td><span class="aicb-tag aicb-tag-provider"><?= esc_html($row->provider ?: '—') ?></span></td>
                        <td style="font-size:11px;color:#666"><?= esc_html( mb_strimwidth($row->model,0,30,'…') ) ?></td>
                        <td><?php echo $row->page_id ? esc_html( get_the_title($row->page_id) ?: '#'.$row->page_id ) : '' ?></td>
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
        <?php if ($pages>1): ?>
            <div style="margin-top:16px">
                <?php for($p=1;$p<=$pages;$p++): ?>
                    <?= $p===$page ? "<strong>$p</strong>" : "<a href='?page=ai-chatbot-logs&paged=$p'>$p</a>" ?>
                    &nbsp;
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else: echo '<p>No logs yet.</p>'; endif; ?>
</div>