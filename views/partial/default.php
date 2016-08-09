<?php
$posts = get_posts([
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'orderby' => 'ID',
    'order'   => 'DESC',
    'post_type' => 'post'
]);
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
                    <?php if ($action == 'search') { ?>
                    'search': search,
                    'replace': replace,
                    <?php } ?>
                };

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

            <?php if ($action == 'search') { ?>
                var search = jQuery('#input-search').val();
                var replace = jQuery('#input-replace').val();
                if (search == '' || replace == '') {
                    alert('You must enter search and replace input !');
                    return false;
                } else {
                    if (confirm("Are you sure you want to search '" + search + "' and replace to '" + replace + "'") != true) {
                        return false;
                    }
                }
            <?php } else { ?>
                if (confirm("Are you sure you want to start '<?php echo $action; ?>' ?") != true) {
                    return false;
                }
            <?php } ?>

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
    <?php if ($action == 'search') { ?>
        <tr>
            <th colspan="6">
                <label for="input-search">Search</label> <input class="input" type="text" id="input-search" />
                <label for="input-replace">Replace</label> <input class="" type="text" id="input-replace" />
            </th>
        </tr>
    <?php } ?>
    <tr>
        <th>#</th>
        <th>ID</th>
        <th>Title</th>
        <th>Created</th>
        <th>Run</th>
        <?php if ($action == 'restore_fru') { ?>
            <th>Featured image</th>
            <th>Status</th>
        <?php } ?>
        <th>Result</th>
    </tr>

    <?php
    foreach ($posts as $i => $post) {
        ?>
        <tr>
            <td><?php echo ($i + 1); ?></td>
            <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
            <td><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank">
                <?php
                    $length = 50;
                    if (strlen($post->post_title) > 50) {
                        echo substr($post->post_title, 0, 50). ' ...';
                    } else {
                        echo $post->post_title;
                    }
                ?>
            </a></td>
            <td><?php echo $post->post_date_gmt; ?></td>
            <td>
                <a target="_blank" class="btn btn-success" href="admin-ajax.php?action=<?php echo $action; ?>_image&id=<?php echo $post->ID; ?>">Run</a>
            </td>
            <td id="result-<?php echo $post->ID; ?>"></td>
        </tr>
    <?php } ?>
</table>