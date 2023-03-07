<h1>Ashley Furniture Settings</h1>
<form action="options.php" method="post">
    <?php 
        settings_fields( 'ashleyfurniture_settings' );
        do_settings_sections( 'ashleyfurniture' );
        submit_button(); 
    ?>
</form>
<p>Please run cron for larger requests</p>
<form action="" method="post">
    <input type="submit" name="fetch-products" value="Fetch Products">
</form>
<?php 
    if(isset($_POST['fetch-products'])) {
        Ashleyfurniture::run_process();
        echo '<br>Process Complete.';
    }
?>