<!-- Output for Plugin Options Page -->
<div class="wrap">
    <?php
    if (isset($_GET['action']) && !empty($_GET['action'])) {
        $action = $_GET['action'];
        echo '<div class="updated below-h2" id="message">';
        switch ($action) {
            case 'grab':
                echo '<h2>Grab images</h2> <p>Images are being grabbed !</p>';
                break;

            case 'download':
                echo '<h2>Download images</h2> <p>Images are being downloaded !</p>';
                break;

            case 'attach':
                echo '<h2>Attach images</h2> <p>Images are being attached !</p>';
                break;

            case 'search':
                echo '<h2>Search / Replace images</h2> <p>Images are being searched and replaced !</p>';
                break;

            case 'regex':
                echo '<h2>Regex images</h2> <p>Images are being replaced from s3 to local !</p>';
                break;
        }
        echo '</div>';
    }
    ?>
    <p>
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=grab" class="nav-link <?php echo ($action == 'grab' ? 'active' : ''); ?>">Grab images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=download" class="nav-link <?php echo ($action == 'download' ? 'active' : ''); ?>">Download images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=attach" class="nav-link <?php echo ($action == 'attach' ? 'active' : ''); ?>">Attach images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=search" class="nav-link <?php echo ($action == 'search' ? 'active' : ''); ?>">Search / Replace images</a>
        </li>
        <li class="nav-item">
            <a href="?page=grab-image&amp;action=regex" class="nav-link <?php echo ($action == 'regex' ? 'active' : ''); ?>">Regex images</a>
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
        case 'grab':
        case 'download':
        case 'attach':
        case 'search':
        case 'regex':
            include_once dirname(__FILE__). '/partial/default.php';
            break;
    }
}