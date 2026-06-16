<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap aicb-wrap">
    <h1>Client AI — Custom Q&A</h1>
    <div class="aicb-section">
        <h2><?= $edit_row ? 'Edit Entry' : 'Add Entry' ?></h2>
        <form method="post">
            <?php wp_nonce_field('aicb_qa_action','aicb_qa_nonce'); ?>
            <input type="hidden" name="aicb_action" value="<?= $edit_row ? 'update' : 'add' ?>">
            <?php if ($edit_row) : ?> <input type="hidden" name="qa_id" value="<?=(int)$edit_row->id?>"> <?php endif; ?>
            <table class="form-table">
                <tr><th>Question keyword(s)</th><td><input type="text" name="question" class="regular-text" required value="<?= $edit_row ? esc_attr($edit_row->question) : '' ?>" /></td></tr>
                <tr><th>Answer</th><td><textarea name="answer" rows="4" class="large-text" required><?= $edit_row ? esc_textarea($edit_row->answer) : '' ?></textarea></td></tr>
            </table>
            <?php submit_button( $edit_row ? 'Update' : 'Add', 'primary', 'submit', false ); ?>
            <?php if ($edit_row) : ?> <a href="?page=ai-chatbot-qa" class="button">Cancel</a> <?php endif; ?>
        </form>
    </div>

    <div class="aicb-section">
        <h2>Entries (<?= count($rows) ?>)</h2>
        <table class="aicb-logs">
            <thead><tr><th>Question</th><th>Answer</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?= esc_html($row->question) ?></td>
                        <td><?= esc_html( mb_strimwidth($row->answer,0,120,'…') ) ?></td>
                        <td><?= $row->active ? '<span style="color:green">Active</span>' : '<span style="color:#999">Inactive</span>' ?></td>
                        <td>
                            <a href="?page=ai-chatbot-qa&edit_id=<?=(int)$row->id?>" class="button button-small">Edit</a>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('aicb_qa_action','aicb_qa_nonce'); ?>
                                <input type="hidden" name="aicb_action" value="toggle">
                                <input type="hidden" name="qa_id" value="<?=(int)$row->id?>">
                                <button class="button button-small"><?= $row->active?'Disable':'Enable' ?></button>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete?')">
                                <?php wp_nonce_field('aicb_qa_action','aicb_qa_nonce'); ?>
                                <input type="hidden" name="aicb_action" value="delete">
                                <input type="hidden" name="qa_id" value="<?=(int)$row->id?>">
                                <button class="button button-small button-link-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>