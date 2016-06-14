<!-- Output for Plugin Options Page -->
<div class="wrap">
    <?php
    if (isset($_GET['action']) && !empty($_GET['action'])) {
        echo '<div class="updated below-h2" id="message">';
        switch ($_GET['action']) {
            case 'download':
                echo '<h2>Download images</h2> <p>Images are being downloaded !</p>';
                break;

            case 'grab':
                echo '<h2>Grab images</h2> <p>Images are being grabbed !</p>';
                break;

            case 'attach':
                echo '<h2>Attach images</h2> <p>Images are being attached !</p>';
                break;

            case 'search':
                echo '<h2>Search / Replace images</h2> <p>Images are being searched and replaced !</p>';
                break;

            case 'restore':
                echo '<h2>Restore featured images</h2> <p>Restore old featured images of frutimian.no !</p>';
                break;
        }
        echo '</div>';
    }
    ?>
    <p>
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=download" class="nav-link <?php echo (@$_GET['action'] == 'download' ? 'active' : ''); ?>">Download images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=grab" class="nav-link <?php echo (@$_GET['action'] == 'grab' ? 'active' : ''); ?>">Grab images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=attach" class="nav-link <?php echo (@$_GET['action'] == 'attach' ? 'active' : ''); ?>">Attach images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=search" class="nav-link <?php echo (@$_GET['action'] == 'search' ? 'active' : ''); ?>">Search / Replace images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=restore" class="nav-link <?php echo (@$_GET['action'] == 'restore' ? 'active' : ''); ?>">Restore featured images</a>
        </li>
        <li class="nav-item">
            <button id="box-status" class="btn btn-warning" style="display: none;"><span class="fa fa-refresh fa-refresh-animate"></span> <span class="percent">Loading...</span></button>
        </li>
    </ul>
    </p>
</div>
<!-- End Output for Plugin Options Page -->

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/css/bootstrap.min.css">
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0-beta1/jquery.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/js/bootstrap.min.js"></script>
<style type="text/css">
    .nav-link {
        cursor: pointer !important;
    }
    #box-status {
        position: fixed;
        right: 10px;
        top: 40px;
    }
    .fa-refresh-animate {
        -animation: spin .7s infinite linear;
        -webkit-animation: spin2 .7s infinite linear;
    }
    @-webkit-keyframes spin2 {
        from { -webkit-transform: rotate(0deg);}
        to { -webkit-transform: rotate(360deg);}
    }
    @keyframes spin {
        from { transform: scale(1) rotate(0deg);}
        to { transform: scale(1) rotate(360deg);}
    }
</style>

<?php
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $action = trim($_GET['action']);
    switch ($action) {
        case 'download':
        case 'grab':
        case 'attach':
        case 'search':
        case 'restore':
            $posts = get_posts([
                'posts_per_page' => -1,
                'post_status' => 'any',
                'orderby' => 'ID',
                'order'   => 'DESC',
                'post_type' => 'post'
            ]);

            if ($action == 'restore') {
                // list of old post_id with thumbnail
                $helper = new grabimage_helper();
                $thumbnail = $helper->get_old_thumbnail();
            }
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

                            <?php if ($action == 'restore') { ?>
                            var restore = jQuery('#restore-' + id);
                            if (restore.attr('status') != 'diff') {
                                if (restore.attr('status') == 'same') {
                                    jQuery('#result-' + id).html('Nothing to do');
                                }
                                doNext();
                                return;
                            }
                            <?php } ?>

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
                        <?php } ?>

                        doNext();
                    });
                });
            </script>
            <table class="table table-striped">
                <tr>
                    <th colspan="5">
                        <button class="btn btn-success" id="button-start">Start</button>
                        <button class="btn btn-danger" id="button-stop">Stop</button>
                    </th>
                </tr>
                <?php if ($action == 'search') { ?>
                    <tr>
                        <th colspan="4">
                            <label for="input-search">Search</label> <input class="input" type="text" id="input-search" />
                            <label for="input-replace">Replace</label> <input class="" type="text" id="input-replace" />
                        </th>
                    </tr>
                <?php } ?>
                <tr>
                    <th>#</th>
                    <th>Post</th>
                    <th>Run</th>
                    <?php if ($action == 'restore') { ?>
                        <th>Featured image</th>
                        <th>Status</th>
                    <?php } ?>
                    <th>Result</th>
                </tr>

                <?php
                foreach ($posts as $i => $post) {
                    $original_url = '';
                    if ($action == 'restore') {
                        if (!isset($thumbnail[$post->ID]) || empty($thumbnail[$post->ID]['attach_url'])) {
                            continue;
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo ($i + 1); ?></td>
                        <td class="post" rel="<?php echo $post->ID; ?>"><a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank"><?php echo $post->ID; ?></a></td>
                        <td>
                            <a target="_blank" class="btn btn-success" href="admin-ajax.php?action=<?php echo $action; ?>_image&id=<?php echo $post->ID; ?>">Run</a>
                        </td>
                        <?php
                        if ($action == 'restore') {
                            echo '<td>';
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
                            echo '</td>';

                            if (empty($current_url)) {
                                $status = 'none';
                            } else {
                                if ($helper->compare_basename($original_url, $current_url)) {
                                    $status = 'same';
                                } else {
                                    $status = 'diff';
                                }
                            }

                            echo "<td id='restore-{$post->ID}' status='{$status}'>";
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
                            echo '</td>';
                        }
                        ?>
                        <td id="result-<?php echo $post->ID; ?>"></td>
                    </tr>
                <?php } ?>

            </table>
            <?php break;
    }
}