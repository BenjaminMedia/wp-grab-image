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
    $thumbnail = $helper->get_old_thumbnail($site);
?>
<script type="text/javascript">
    jQuery(document).ready(function () {
        var post = jQuery(".post");
        var index = -1;
        var count = -1;
        var total = post.length;
        var loading = jQuery('#box-status');

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
                    loading.hide();
                    return;
                } else {
                    count = index;
                    loading.show();
                }

                // update percent status
                loading.find('.percent').html(' Finished ' + count + '/' + total + ' = ' + parseFloat(100.0 * count / total).toFixed(2) + '%');

                // element selector
                var current = post.eq(index); // current row element
                var id = current.attr('rel'); console.log(id); // current post ID
                var url = jQuery('#url-' + id); // current row url image
                var status = jQuery('#status-' + id); // current row status
                var result = jQuery('#result-' + id); // current row result

                // ignore same url
                if (status.attr('status') != 'diff') {
                    if (status.attr('status') == 'same') {
                        result.html('Nothing to do');
                    }
                    doNext();
                    return;
                }

                // call ajax request
                result.html('Loading ...');
                jQuery.ajax({
                    url: ajaxurl,
                    cache: false,
                    data: {
                        'action'    : '<?php echo $action . '_image'; ?>',
                        'id'        : id,
                        'url'       : url.attr('url')
                    },
                    method: 'POST',
                    success: function(msg) {
                        console.log(msg);
                        result.html(msg);
                        doNext();
                    },
                    error: function (msg) {
                        console.log(msg);
                        result.html('Error ... Restart in 10s');
                        --index;
                        setTimeout(doNext, 10000);
                    }
                });
            }

            if (confirm("<?php echo ($site == 'frut' ? 'This function is only for frutimian.no site. ' : '') ?>Are you sure you want to start ?") != true) {
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
        if ($site == 'frut') {
            if (!isset($thumbnail[$post->ID]) || empty($thumbnail[$post->ID]['attach_url'])) {
                continue;
            } else {
                $original_url = $thumbnail[$post->ID]['attach_url'];
            }
        } else {
            $current_post_url = get_the_permalink($post);
            $original_post_url = $helper->get_original_post_url($current_post_url, 'http://sarahlouise.dk');
            if (!isset($thumbnail[$original_post_url]) || empty($thumbnail[$original_post_url])) {
                continue;
            } else {
                $original_url = $thumbnail[$original_post_url];
            }
        }
        ?>
        <tr>
            <td><?php echo ($i + 1); ?></td>
            <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
            <td>
                <a target="_blank" class="btn btn-success" href="admin-ajax.php?action=<?php echo $action; ?>_image&id=<?php echo $post->ID; ?>">Run</a>
            </td>
            <td id="url-<?php echo $post->ID; ?>" url="<?php echo urlencode($original_url); ?>">
                <?php
                    echo 'Original : ';
                    echo "<a href='{$original_url}' target='_blank'>{$original_url}</a>";

                    // current featured image url
                    $current_url = get_the_post_thumbnail_url($post->ID, 'full');
                    if (!empty($current_url)) {
                        echo '<br/>Current : ';
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
            <td id="status-<?php echo $post->ID; ?>" status="<?php echo $status; ?>">
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