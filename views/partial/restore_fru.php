<?php
    $posts = get_posts([
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'ID',
        'order'   => 'DESC',
        'post_type' => 'post'
    ]);

    // list of old post_id with thumbnail
    $helper = new grabimage_helper();
    $thumbnail = $helper->get_old_thumbnail();
?>
<script type="text/javascript">
    jQuery(document).ready(function () {
        var post = jQuery(".post");
        var index = -1;
        var count = -1;
        var total = post.length;

        /**
         * click stop button
         */
        jQuery('#button-stop').click(function () {
            index = post.length;
        });

        /**
         * click start button
         */
        jQuery('#button-start').click(function () {
            if (count < post.length) {
                index = count;
            }
            function doNext() {
                if (++index >= post.length) {
                    jQuery('#box-status').hide();
                    return;
                } else {
                    count = index;
                    jQuery('#box-status').show();
                }

                // update percent status
                jQuery('#box-status .percent').html(' Finished ' + count + '/' + total + ' = ' + parseFloat(100.0 * count / total).toFixed(2) + '%');

                var current = post.eq(index);
                var id = current.attr('rel');
                var data = {
                    'action': '<?php echo $action . '_image'; ?>',
                    'id': id,
                };
                var restore = jQuery('#restore-' + id);
                if (restore.attr('status') != 'diff') {
                    if (restore.attr('status') == 'same') {
                        jQuery('#result-' + id).html('Nothing to do');
                    }
                    doNext();
                    return;
                }

                console.log(id);
                jQuery('#result-' + id).html('Loading ...');
                jQuery.ajax({
                    url: ajaxurl,
                    cache: false,
                    data: data,
                    method: 'POST',
                    success: function(msg) {
                        console.log(msg);
                        jQuery('#result-' + id).html(msg);
                        doNext();
                    },
                    error: function (msg) {
                        console.log(msg);
                        jQuery('#result-' + id).html('Error ... Restart in 10s');
                        --index;
                        setTimeout(doNext, 10000);
                    }
                });
            }

            if (confirm("This function is only for frutimian.no site. Are you sure you want to start ?") != true) {
                return false;
            }

            doNext();
        });
    });
</script>
<table class="table table-striped">
    <tr>
        <th colspan="6">
            <button class="btn btn-success" id="button-start">Start</button>
            <button class="btn btn-danger" id="button-stop">Stop</button>
        </th>
    </tr>
    <tr>
        <th>#</th>
        <th>Post</th>
        <th>Run</th>
        <th>Featured image</th>
        <th>Status</th>
        <th>Result</th>
    </tr>

    <?php
    foreach ($posts as $i => $post) {
        $original_url = '';
        if (!isset($thumbnail[$post->ID]) || empty($thumbnail[$post->ID]['attach_url'])) {
            continue;
        }
        ?>
        <tr>
            <td><?php echo ($i + 1); ?></td>
            <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
            <td>
                <a target="_blank" class="btn btn-success" href="admin-ajax.php?action=<?php echo $action; ?>_image&id=<?php echo $post->ID; ?>">Run</a>
            </td>
            <td>
                <?php
                    if (isset($thumbnail[$post->ID])) {
                        $original_url = $thumbnail[$post->ID]['attach_url'];
                        if (!empty($original_url)) {
                            echo 'Original : ';
                            echo "<a href='{$original_url}' target='_blank'>{$original_url}</a>";
                        }
                    }

                    $current_url = get_the_post_thumbnail_url($post->ID, 'full');
                    if (!empty($current_url)) {
                        if (!empty($original_url)) {
                            echo '<br/>';
                        }
                        echo 'Current : ';
                        echo "<a href='{$current_url}' target='_blank'>{$current_url}</a>";
                    }
                ?>
            </td>
                <?php
                    if (empty($current_url)) {
                        $status = 'none';
                    } else {
                        if ($helper->compare_basename($original_url, $current_url)) {
                            $status = 'same';
                        } else {
                            $status = 'diff';
                        }
                    }
                ?>
            <td id='restore-{$post->ID}' status='{$status}'>
                <?php
                    switch ($status) {
                        case 'none':
                            echo '<span attr-status="none" class="label label-default">No featured image</span>';
                            break;

                        case 'same':
                            echo '<span attr-status="same" class="label label-success">Same featured image</span>';
                            break;

                        case 'diff':
                            echo '<span attr-status="diff" class="label label-danger">Different featured image</span>';
                            break;
                    }
                ?>
            </td>
            <td id="result-<?php echo $post->ID; ?>"></td>
        </tr>
    <?php } ?>
</table>